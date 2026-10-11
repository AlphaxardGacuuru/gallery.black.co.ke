<?php

namespace Tests\Feature;

use App\Http\Services\MPESATransactionService;
use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtraPhotoPurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableExtraPhoto(int $price = 20): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'photo_extra_photo'],
            ['value' => ['enabled' => true, 'price' => $price]]
        );
    }

    public function test_buying_a_photo_slot_fails_when_the_feature_is_disabled(): void
    {
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_buying_a_photo_slot_fails_without_an_active_competition(): void
    {
        $this->enableExtraPhoto();
        $user = User::factory()->create(['phone' => '0700123456']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_buying_a_photo_slot_fails_without_a_phone_number(): void
    {
        $this->enableExtraPhoto();
        PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_buying_a_photo_slot_succeeds_without_any_submitted_entry(): void
    {
        $this->enableExtraPhoto(20);
        PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);

        $this->partialMock(MPESATransactionService::class, function ($mock) {
            $mock->shouldReceive('stkPush')->once()->andReturn([
                'success',
                'Request Sent to Your Phone',
                ['location' => 'https://sandbox.kopokopo.com/api/v1/incoming_payments/photo-1'],
            ]);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_buying_a_photo_slot_stores_the_type_price_and_reference(): void
    {
        $this->enableExtraPhoto(20);
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);

        $this->partialMock(MPESATransactionService::class, function ($mock) {
            $mock->shouldReceive('stkPush')
                ->once()
                ->withArgs(fn($request) => (int) $request->input('amount') === 20)
                ->andReturn([
                    'success',
                    'Request Sent to Your Phone',
                    ['location' => 'https://sandbox.kopokopo.com/api/v1/incoming_payments/photo-1'],
                ]);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertOk()
            ->assertJsonPath('status', true);

        $purchase = PhotoSlotPurchase::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(PhotoSlotPurchase::TYPE_EXTRA_PHOTO, $purchase->type);
        $this->assertSame(20, $purchase->amount);
        $this->assertSame('photo-1', $purchase->kopokopo_reference);
        $this->assertSame($competition->id, $purchase->competition_id);
    }

    public function test_buying_a_photo_slot_fails_with_a_fresh_pending_purchase(): void
    {
        $this->enableExtraPhoto();
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);
        PhotoSlotPurchase::factory()->extraPhoto()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
            'status' => PhotoSlotPurchase::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_buying_a_photo_slot_succeeds_once_a_pending_purchase_goes_stale(): void
    {
        $this->enableExtraPhoto();
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);
        $stale = PhotoSlotPurchase::factory()->extraPhoto()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
            'status' => PhotoSlotPurchase::STATUS_PENDING,
            'created_at' => now()->subMinutes(PhotoSlotPurchase::PENDING_TTL_MINUTES + 1),
        ]);

        $this->partialMock(MPESATransactionService::class, function ($mock) {
            $mock->shouldReceive('stkPush')->once()->andReturn([
                'success',
                'Request Sent to Your Phone',
                ['location' => 'https://sandbox.kopokopo.com/api/v1/incoming_payments/photo-2'],
            ]);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertOk()
            ->assertJsonPath('status', true);

        // The stale row is left exactly as it was, never auto-failed, in
        // case Kopokopo's webhook for it still shows up later.
        $this->assertSame(PhotoSlotPurchase::STATUS_PENDING, $stale->fresh()->status);
        $this->assertSame(2, PhotoSlotPurchase::where('user_id', $user->id)->count());
    }

    public function test_buying_a_photo_slot_is_repeatable_after_a_paid_purchase(): void
    {
        $this->enableExtraPhoto();
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);
        PhotoSlotPurchase::factory()->paid()->extraPhoto()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
        ]);

        $this->partialMock(MPESATransactionService::class, function ($mock) {
            $mock->shouldReceive('stkPush')->once()->andReturn([
                'success',
                'Request Sent to Your Phone',
                ['location' => 'https://sandbox.kopokopo.com/api/v1/incoming_payments/photo-3'],
            ]);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_buying_a_photo_slot_fails_at_the_maximum_of_four_paid_purchases(): void
    {
        $this->enableExtraPhoto();
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);
        PhotoSlotPurchase::factory()->paid()->extraPhoto()->count(4)->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_an_extra_entry_purchase_does_not_block_buying_a_photo_slot(): void
    {
        $this->enableExtraPhoto();
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);
        PhotoSlotPurchase::factory()->paid()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
        ]);

        $this->partialMock(MPESATransactionService::class, function ($mock) {
            $mock->shouldReceive('stkPush')->once()->andReturn([
                'success',
                'Request Sent to Your Phone',
                ['location' => 'https://sandbox.kopokopo.com/api/v1/incoming_payments/photo-4'],
            ]);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_a_failed_stk_push_marks_the_purchase_failed(): void
    {
        $this->enableExtraPhoto();
        PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);

        $this->partialMock(MPESATransactionService::class, function ($mock) {
            $mock->shouldReceive('stkPush')->once()->andReturn(['error', 'Request Failed', ['data' => []]]);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/extra-photo-purchases')
            ->assertOk()
            ->assertJsonPath('status', false);

        $purchase = PhotoSlotPurchase::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(PhotoSlotPurchase::STATUS_FAILED, $purchase->status);
    }

    public function test_a_user_can_only_view_their_own_photo_purchase(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $purchase = PhotoSlotPurchase::factory()->extraPhoto()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/photo-slot-purchases/{$purchase->id}")
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/photo-slot-purchases/{$purchase->id}")
            ->assertOk()
            ->assertJsonPath('data.status', $purchase->status);
    }
}
