<?php

namespace Tests\Feature\Listeners;

use App\Events\MpesaTransactionCreatedEvent;
use App\Listeners\GrantPhotoSlotListener;
use App\Models\MPESATransaction;
use App\Models\PhotoSlotPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrantPhotoSlotListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_the_matching_pending_purchase_as_paid(): void
    {
        $purchase = PhotoSlotPurchase::factory()->create([
            'status' => PhotoSlotPurchase::STATUS_PENDING,
            'kopokopo_reference' => 'abc-123',
        ]);
        $mpesaTransaction = MPESATransaction::factory()->create([
            'user_id' => User::factory()->create()->id,
            'kopokopo_id' => 'abc-123',
        ]);

        (new GrantPhotoSlotListener)
            ->handle(new MpesaTransactionCreatedEvent($mpesaTransaction));

        $purchase->refresh();
        $this->assertSame(PhotoSlotPurchase::STATUS_PAID, $purchase->status);
        $this->assertSame($mpesaTransaction->id, $purchase->mpesa_transaction_id);
        $this->assertNotNull($purchase->paid_at);
    }

    public function test_it_ignores_a_non_matching_reference(): void
    {
        $purchase = PhotoSlotPurchase::factory()->create([
            'status' => PhotoSlotPurchase::STATUS_PENDING,
            'kopokopo_reference' => 'abc-123',
        ]);
        $mpesaTransaction = MPESATransaction::factory()->create([
            'user_id' => User::factory()->create()->id,
            'kopokopo_id' => 'does-not-match',
        ]);

        (new GrantPhotoSlotListener)
            ->handle(new MpesaTransactionCreatedEvent($mpesaTransaction));

        $purchase->refresh();
        $this->assertSame(PhotoSlotPurchase::STATUS_PENDING, $purchase->status);
        $this->assertNull($purchase->mpesa_transaction_id);
    }

    public function test_it_ignores_a_purchase_that_is_already_paid(): void
    {
        $purchase = PhotoSlotPurchase::factory()->paid()->create([
            'kopokopo_reference' => 'abc-123',
        ]);
        $originalPaidAt = $purchase->paid_at;
        $mpesaTransaction = MPESATransaction::factory()->create([
            'user_id' => User::factory()->create()->id,
            'kopokopo_id' => 'abc-123',
        ]);

        (new GrantPhotoSlotListener)
            ->handle(new MpesaTransactionCreatedEvent($mpesaTransaction));

        $purchase->refresh();
        $this->assertNull($purchase->mpesa_transaction_id);
        $this->assertTrue($originalPaidAt->equalTo($purchase->paid_at));
    }
}
