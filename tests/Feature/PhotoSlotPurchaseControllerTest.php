<?php

namespace Tests\Feature;

use App\Http\Services\MPESATransactionService;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use App\Models\Setting;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoSlotPurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableExtraSlot(int $price = 50): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'photo_extra_slot'],
            ['value' => ['enabled' => true, 'price' => $price]]
        );
    }

    private function submitPhoto(User $user, PhotoCompetition $competition): Photo
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('photo.jpg');
        $path = $file->store('temporary-uploads/photos', 'public');
        $temporaryUpload = TemporaryUpload::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return Photo::create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
            'disk' => $temporaryUpload->disk,
            'path' => $temporaryUpload->path,
            'caption' => 'Entry',
        ]);
    }

    public function test_buying_a_slot_fails_when_the_feature_is_disabled(): void
    {
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);
        $this->submitPhoto($user, $competition);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/photo-slot-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_buying_a_slot_fails_without_an_active_competition(): void
    {
        $this->enableExtraSlot();
        $user = User::factory()->create(['phone' => '0700123456']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/photo-slot-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_buying_a_slot_fails_without_a_phone_number(): void
    {
        $this->enableExtraSlot();
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/photo-slot-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_buying_a_slot_fails_before_submitting_the_free_entry(): void
    {
        $this->enableExtraSlot();
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/photo-slot-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_buying_a_slot_fails_once_both_entries_are_used(): void
    {
        $this->enableExtraSlot();
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);
        $this->submitPhoto($user, $competition);
        $this->submitPhoto($user, $competition);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/photo-slot-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_buying_a_slot_fails_with_an_existing_pending_purchase(): void
    {
        $this->enableExtraSlot();
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);
        $this->submitPhoto($user, $competition);
        PhotoSlotPurchase::factory()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
            'status' => PhotoSlotPurchase::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/photo-slot-purchases')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('competition');
    }

    public function test_buying_a_slot_starts_an_stk_push_and_records_the_reference(): void
    {
        $this->enableExtraSlot(75);
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);
        $this->submitPhoto($user, $competition);

        // stkPush() builds its own K2 SDK client internally with no DI seam
        // (same as KopokopoTransferService::initiateTransfer() elsewhere in
        // this app), so the real network boundary is mocked at the service
        // method rather than at the SDK, asserting the amount it was
        // called with matches the admin-configured price.
        $this->partialMock(MPESATransactionService::class, function ($mock) {
            $mock->shouldReceive('stkPush')
                ->once()
                ->withArgs(fn($request) => (int) $request->input('amount') === 75)
                ->andReturn([
                    'success',
                    'Request Sent to Your Phone',
                    ['location' => 'https://sandbox.kopokopo.com/api/v1/incoming_payments/abc-123'],
                ]);
        });

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/photo-slot-purchases');

        $response->assertOk()->assertJsonPath('status', true);

        $purchase = PhotoSlotPurchase::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(PhotoSlotPurchase::STATUS_PENDING, $purchase->status);
        $this->assertSame('abc-123', $purchase->kopokopo_reference);
        $this->assertSame(75, $purchase->amount);
    }

    public function test_a_failed_stk_push_marks_the_purchase_failed(): void
    {
        $this->enableExtraSlot();
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create(['phone' => '0700123456']);
        $this->submitPhoto($user, $competition);

        $this->partialMock(MPESATransactionService::class, function ($mock) {
            $mock->shouldReceive('stkPush')
                ->once()
                ->andReturn(['error', 'Request Failed', ['data' => []]]);
        });

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/photo-slot-purchases');

        $response->assertOk()->assertJsonPath('status', false);

        $purchase = PhotoSlotPurchase::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(PhotoSlotPurchase::STATUS_FAILED, $purchase->status);
    }

    public function test_a_user_can_only_view_their_own_purchase(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $purchase = PhotoSlotPurchase::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/photo-slot-purchases/{$purchase->id}")
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/photo-slot-purchases/{$purchase->id}")
            ->assertOk()
            ->assertJsonPath('data.status', $purchase->status);
    }
}
