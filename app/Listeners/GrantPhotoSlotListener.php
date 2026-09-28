<?php

namespace App\Listeners;

use App\Events\MpesaTransactionCreatedEvent;
use App\Models\PhotoSlotPurchase;

class GrantPhotoSlotListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * If this transaction's Kopokopo reference matches a pending extra-slot
     * purchase, mark it paid. Every incoming M-Pesa transaction fires this,
     * transactions unrelated to a slot purchase simply match nothing.
     */
    public function handle(MpesaTransactionCreatedEvent $event): void
    {
        $purchase = PhotoSlotPurchase::where(
            'kopokopo_reference',
            $event->mpesaTransaction->kopokopo_id
        )
            ->where('status', PhotoSlotPurchase::STATUS_PENDING)
            ->first();

        if (! $purchase) {
            return;
        }

        $purchase->update([
            'status' => PhotoSlotPurchase::STATUS_PAID,
            'mpesa_transaction_id' => $event->mpesaTransaction->id,
            'paid_at' => now(),
        ]);
    }
}
