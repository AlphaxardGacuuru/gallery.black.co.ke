<?php

namespace Tests\Feature;

use App\Models\MPESATransaction;
use App\Models\PhotoSlotPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaTransactionWebhookTest extends TestCase
{
    use RefreshDatabase;

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
