<?php

namespace Tests\Feature;

use App\Models\MPESATransaction;
use App\Models\PhotoSlotPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MpesaTransactionWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function postFailedPayment(string $reference, mixed $errors): void
    {
        $this->postJson('/api/mpesa-transactions', [
            'data' => [
                'id' => $reference,
                'type' => 'incoming_payment',
                'attributes' => [
                    'initiation_time' => now()->toIso8601String(),
                    'status' => 'Failed',
                    'event' => [
                        'type' => 'Incoming Payment Request',
                        'resource' => null,
                        'errors' => $errors,
                    ],
                    'metadata' => null,
                ],
            ],
        ])->assertOk();
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function failureReasons(): array
    {
        return [
            'cancelled (seen in production)' => [['Request Cancelled by user.'], 'cancelled'],
            'phone unreachable (seen in production)' => [['DS timeout user cannot be reached.'], 'unreachable'],
            'low balance (seen in the sandbox)' => [['The balance is insufficient for the transaction.'], 'insufficient_funds'],
            'wrong PIN' => [['The initiator information is invalid.'], 'wrong_pin'],
            'something else' => [['Unexpected system error.'], 'unknown'],
            'no reason given' => [null, 'unknown'],
            'a plain string, as in some Kopokopo docs' => ['Request Cancelled by user.', 'cancelled'],
        ];
    }

    #[DataProvider('failureReasons')]
    public function test_the_buyer_is_told_why_a_slot_payment_failed(mixed $errors, string $expectedReason): void
    {
        $purchase = PhotoSlotPurchase::factory()->create([
            'kopokopo_reference' => 'failed-payment',
        ]);

        $this->postFailedPayment('failed-payment', $errors);

        $this->actingAs($purchase->user, 'sanctum')
            ->getJson("/api/photo-slot-purchases/{$purchase->id}")
            ->assertOk()
            ->assertJsonPath('data.status', PhotoSlotPurchase::STATUS_FAILED)
            ->assertJsonPath('data.failureReason', $expectedReason);
    }

    public function test_an_unanswered_prompt_reads_as_timed_out_and_a_pending_one_has_no_reason(): void
    {
        $pending = PhotoSlotPurchase::factory()->create();
        $stale = PhotoSlotPurchase::factory()->create([
            'created_at' => now()->subMinutes(PhotoSlotPurchase::PENDING_TTL_MINUTES + 1),
        ]);

        $this->actingAs($pending->user, 'sanctum')
            ->getJson("/api/photo-slot-purchases/{$pending->id}")
            ->assertJsonPath('data.status', PhotoSlotPurchase::STATUS_PENDING)
            ->assertJsonPath('data.failureReason', null);

        $this->actingAs($stale->user, 'sanctum')
            ->getJson("/api/photo-slot-purchases/{$stale->id}")
            ->assertJsonPath('data.status', PhotoSlotPurchase::STATUS_EXPIRED)
            ->assertJsonPath('data.failureReason', 'timed_out');
    }

    public function test_a_cancelled_stk_push_fails_its_pending_purchase_instead_of_crashing(): void
    {
        $purchase = PhotoSlotPurchase::factory()->create([
            'kopokopo_reference' => '00315eaf-6a9d-4623-88ca-7713bf42e64b',
        ]);
        $unrelated = PhotoSlotPurchase::factory()->create([
            'kopokopo_reference' => 'some-other-payment',
        ]);

        // The exact payload Kopokopo sent in production when a user
        // cancelled the prompt.
        $this->postJson('/api/mpesa-transactions', [
            'data' => [
                'id' => '00315eaf-6a9d-4623-88ca-7713bf42e64b',
                'type' => 'incoming_payment',
                'attributes' => [
                    'initiation_time' => '2026-10-15T08:58:00.031+03:00',
                    'status' => 'Failed',
                    'event' => [
                        'type' => 'Incoming Payment Request',
                        'resource' => null,
                        'errors' => ['Request Cancelled by user.'],
                    ],
                    'metadata' => null,
                    '_links' => [
                        'callback_url' => 'https://gallery.black.co.ke/api/mpesa-transactions',
                        'self' => 'https://api.kopokopo.com/api/v2/incoming_payments/00315eaf-6a9d-4623-88ca-7713bf42e64b',
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertSame(PhotoSlotPurchase::STATUS_FAILED, $purchase->fresh()->status);
        $this->assertSame(PhotoSlotPurchase::STATUS_PENDING, $unrelated->fresh()->status);
        $this->assertSame(0, MPESATransaction::query()->count());
    }

    public function test_a_payment_from_an_unknown_phone_is_ignored_instead_of_crashing(): void
    {
        $this->postJson('/api/mpesa-transactions', [
            'data' => [
                'id' => 'payment-from-a-stranger',
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
                            'sender_phone_number' => '+254799999999',
                            'amount' => '50.0',
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

        $this->assertSame(0, MPESATransaction::query()->count());
    }
}
