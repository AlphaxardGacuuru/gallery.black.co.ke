<?php

namespace Tests\Feature;

use App\Events\KopokopoTransferInitiated;
use App\Http\Services\KopokopoTransferService;
use App\Http\Services\MPESATransactionService;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoPurchase;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PhotoPurchasedNotification;
use App\Notifications\PhotoSoldNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private function enableSales(int $price = 200, int $photographerShare = 70): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'photo_sale'],
            ['value' => ['enabled' => true, 'price' => $price, 'photographerShare' => $photographerShare]]
        );
    }

    private ?PhotoCompetition $endedCompetition = null;

    private function endedPhoto(array $attributes = []): Photo
    {
        $this->endedCompetition ??= PhotoCompetition::factory()->ended()->create([
            'starts_at' => now()->subWeeks(2),
            'ends_at' => now()->subWeeks(2)->addDays(4),
        ]);

        return Photo::factory()->create([
            'competition_id' => $this->endedCompetition->id,
            'user_id' => User::factory()->create(['phone' => '07' . fake()->unique()->numerify('########')])->id,
            ...$attributes,
        ]);
    }

    private function mockStkPush(string $location = 'https://api.kopokopo.com/api/v1/incoming_payments/photo-payment-1'): void
    {
        $this->partialMock(MPESATransactionService::class, function ($mock) use ($location) {
            $mock->shouldReceive('stkPush')->once()->andReturn([
                'success',
                'Request Sent to Your Phone',
                ['status' => 'success', 'location' => $location],
            ]);
        });
    }

    private function postPaymentWebhook(string $kopokopoId, string $phone, int $amount): void
    {
        $this->postJson('/api/mpesa-transactions', [
            'data' => [
                'id' => $kopokopoId,
                'type' => 'incoming_payment',
                'attributes' => [
                    'initiation_time' => now()->toIso8601String(),
                    'status' => 'Success',
                    'event' => [
                        'type' => 'Incoming Payment Request',
                        'resource' => [
                            'id' => 'resource-1',
                            'reference' => 'QCL160124A7',
                            'origination_time' => now()->toIso8601String(),
                            'sender_phone_number' => '+254' . substr($phone, 1),
                            'amount' => (string) $amount,
                            'currency' => 'KES',
                            'till_number' => 'K000000',
                            'system' => 'Lipa Na M-PESA',
                            'status' => 'Received',
                            'sender_first_name' => 'Jane',
                            'sender_middle_name' => null,
                            'sender_last_name' => 'Doe',
                        ],
                        'errors' => null,
                    ],
                    'metadata' => null,
                ],
            ],
        ])->assertOk();
    }

    public function test_buying_starts_an_stk_push_for_the_admin_price(): void
    {
        $this->enableSales(price: 250, photographerShare: 60);
        $photo = $this->endedPhoto();
        $buyer = User::factory()->create(['phone' => '0700123456']);
        $this->mockStkPush();

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/photos/{$photo->id}/purchases")
            ->assertOk()
            ->assertJsonPath('status', true);

        $purchase = PhotoPurchase::sole();
        $this->assertSame(PhotoPurchase::STATUS_PENDING, $purchase->status);
        $this->assertSame(250, $purchase->amount);
        $this->assertSame(150, $purchase->seller_amount);
        $this->assertSame($photo->user_id, $purchase->seller_id);
        $this->assertSame('photo-payment-1', $purchase->kopokopo_reference);
    }

    public function test_photos_cannot_be_bought_when_sales_are_disabled(): void
    {
        $photo = $this->endedPhoto();
        $buyer = User::factory()->create(['phone' => '0700123456']);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/photos/{$photo->id}/purchases")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_unsplash_photos_own_photos_and_active_entries_are_not_for_sale(): void
    {
        $this->enableSales();
        $buyer = User::factory()->create(['phone' => '0700123456']);

        $unsplash = $this->endedPhoto(['unsplash_id' => 'abc123']);
        $own = $this->endedPhoto(['user_id' => $buyer->id]);
        $active = Photo::factory()->create([
            'competition_id' => PhotoCompetition::factory()->create()->id,
        ]);

        foreach ([$unsplash, $own, $active] as $photo) {
            $this->actingAs($buyer, 'sanctum')
                ->postJson("/api/photos/{$photo->id}/purchases")
                ->assertUnprocessable()
                ->assertJsonValidationErrors('photo');
        }

        $this->assertSame(0, PhotoPurchase::count());
    }

    public function test_a_photo_cannot_be_bought_twice(): void
    {
        $this->enableSales();
        $photo = $this->endedPhoto();
        $buyer = User::factory()->create(['phone' => '0700123456']);
        PhotoPurchase::factory()->paid()->create(['photo_id' => $photo->id, 'buyer_id' => $buyer->id]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/photos/{$photo->id}/purchases")
            ->assertUnprocessable()
            ->assertJsonPath('errors.photo.0', 'You already own this photo.');
    }

    public function test_a_confirmed_payment_marks_the_purchase_paid_pays_the_photographer_and_notifies_both(): void
    {
        Notification::fake();
        $this->enableSales();
        $photo = $this->endedPhoto();
        $buyer = User::factory()->create(['phone' => '0700123456']);
        $purchase = PhotoPurchase::factory()->create([
            'photo_id' => $photo->id,
            'buyer_id' => $buyer->id,
            'amount' => 200,
            'seller_amount' => 140,
            'kopokopo_reference' => 'photo-payment-1',
        ]);

        $this->partialMock(KopokopoTransferService::class, function ($mock) use ($photo) {
            $mock->shouldReceive('initiateTransfer')
                ->once()
                ->withArgs(fn($request) => (int) $request->input('amount') === 140
                    && $request->input('destinationReference') === $photo->user->phone)
                ->andReturn([true, 'Transfer initiated', [
                    'status' => 'success',
                    'location' => 'https://api.kopokopo.com/api/v1/send_money/photo-payout-1',
                ]]);
        });

        $this->postPaymentWebhook('photo-payment-1', '0700123456', 200);

        $purchase->refresh();
        $this->assertSame(PhotoPurchase::STATUS_PAID, $purchase->status);
        $this->assertNotNull($purchase->paid_at);
        $this->assertSame('photo-payout-1', $purchase->payout_reference);
        $this->assertNull($purchase->payout_paid_at);

        Notification::assertSentTo($buyer, PhotoPurchasedNotification::class);
        Notification::assertSentTo($photo->user, PhotoSoldNotification::class, function ($notification) use ($photo) {
            return str_contains($notification->toArray($photo->user)['message'], 'on its way');
        });
    }

    public function test_a_photographer_without_a_phone_is_asked_to_add_one(): void
    {
        Notification::fake();
        $photo = $this->endedPhoto();
        $photo->user->update(['phone' => null]);
        $buyer = User::factory()->create(['phone' => '0700123456']);
        $purchase = PhotoPurchase::factory()->create([
            'photo_id' => $photo->id,
            'buyer_id' => $buyer->id,
            'kopokopo_reference' => 'photo-payment-2',
        ]);

        $this->postPaymentWebhook('photo-payment-2', '0700123456', 200);

        $this->assertSame(PhotoPurchase::STATUS_PAID, $purchase->fresh()->status);
        $this->assertNull($purchase->fresh()->payout_reference);
        Notification::assertSentTo($photo->user, PhotoSoldNotification::class, function ($notification) use ($photo) {
            return str_contains($notification->toArray($photo->user)['message'], 'Add your M-Pesa phone number');
        });
    }

    public function test_a_failed_payment_fails_the_pending_purchase(): void
    {
        $purchase = PhotoPurchase::factory()->create(['kopokopo_reference' => 'photo-payment-3']);

        $this->postJson('/api/mpesa-transactions', [
            'data' => [
                'id' => 'photo-payment-3',
                'type' => 'incoming_payment',
                'attributes' => [
                    'initiation_time' => now()->toIso8601String(),
                    'status' => 'Failed',
                    'event' => [
                        'type' => 'Incoming Payment Request',
                        'resource' => null,
                        'errors' => ['Request Cancelled by user.'],
                    ],
                    'metadata' => null,
                ],
            ],
        ])->assertOk();

        $this->assertSame(PhotoPurchase::STATUS_FAILED, $purchase->fresh()->status);
    }

    public function test_a_confirmed_transfer_marks_the_photographer_paid(): void
    {
        Event::fake([KopokopoTransferInitiated::class]);
        $purchase = PhotoPurchase::factory()->paid()->create([
            'photo_id' => $this->endedPhoto()->id,
            'seller_amount' => 140,
            'payout_reference' => 'photo-payout-1',
        ]);

        $this->postJson('/api/kopokopo-transfers', [
            'data' => [
                'id' => 'photo-payout-1',
                'attributes' => [
                    'status' => 'Processed',
                    'created_at' => now()->toIso8601String(),
                    'currency' => 'KES',
                    'destinations' => [['amount' => 140]],
                    'transfer_batches' => [['status' => 'Transferred']],
                    'metadata' => [],
                ],
            ],
        ])->assertOk();

        $this->assertNotNull($purchase->fresh()->payout_paid_at);
        Event::assertDispatched(
            KopokopoTransferInitiated::class,
            fn(KopokopoTransferInitiated $event): bool => $event->amount === 140.0,
        );
    }

    public function test_only_buyers_and_the_photographer_get_download_links(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('original.jpg')->store('photos', 'public');
        $photo = $this->endedPhoto(['path' => $path]);
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        PhotoPurchase::factory()->paid()->create(['photo_id' => $photo->id, 'buyer_id' => $buyer->id]);

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/photos/{$photo->id}/downloads")
            ->assertForbidden();

        $this->actingAs($photo->user, 'sanctum')
            ->getJson("/api/photos/{$photo->id}/downloads")
            ->assertOk();

        $url = $this->actingAs($buyer, 'sanctum')
            ->getJson("/api/photos/{$photo->id}/downloads")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data.0.url');

        $this->get($url)->assertOk()->assertDownload("black-gallery-{$photo->id}-1.jpg");

        // An unsigned link to the same file is refused.
        $this->get("/api/photos/{$photo->id}/download")->assertForbidden();
    }

    public function test_discover_tells_the_viewer_what_is_for_sale_and_what_they_own(): void
    {
        $this->enableSales(price: 300);
        $owned = $this->endedPhoto();
        $unsplash = $this->endedPhoto(['unsplash_id' => 'abc123']);
        $viewer = User::factory()->create();
        PhotoPurchase::factory()->paid()->create(['photo_id' => $owned->id, 'buyer_id' => $viewer->id]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/photos/discover')->assertOk();

        $response->assertJsonPath('sale.enabled', true)->assertJsonPath('sale.price', 300);
        $photos = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($photos[$owned->id]['isSellable']);
        $this->assertTrue($photos[$owned->id]['isPurchasedByViewer']);
        $this->assertFalse($photos[$unsplash->id]['isSellable']);
        $this->assertFalse($photos[$unsplash->id]['isPurchasedByViewer']);
    }
}
