<?php

namespace App\Listeners;

use App\Enums\UserEventName;
use App\Events\MpesaTransactionCreatedEvent;
use App\Models\PhotoSlotPurchase;
use App\Services\EventRecorder;

class GrantPhotoSlotListener
{
    /**
     * Create the event listener.
     */
    public function __construct(protected EventRecorder $eventRecorder)
    {
        //
    }

    /**
     * If this transaction's Kopokopo reference matches a pending (or
     * expired, for a payment that landed late) extra-slot purchase, mark
     * it paid. Paid rows never match again, so a repeated webhook is a no-op. Every incoming M-Pesa transaction fires this,
     * transactions unrelated to a slot purchase simply match nothing.
     */
    public function handle(MpesaTransactionCreatedEvent $event): void
    {
        $purchase = PhotoSlotPurchase::where(
            'kopokopo_reference',
            $event->mpesaTransaction->kopokopo_id
        )
            ->whereIn('status', PhotoSlotPurchase::UNSETTLED_STATUSES)
            ->first();

        if (! $purchase) {
            return;
        }

        $purchase->update([
            'status' => PhotoSlotPurchase::STATUS_PAID,
            'mpesa_transaction_id' => $event->mpesaTransaction->id,
            'paid_at' => now(),
        ]);

        $this->recordEvent($purchase);
    }

    private function recordEvent(PhotoSlotPurchase $purchase): void
    {
        if (! $purchase->user) {
            return;
        }

        $this->eventRecorder->record(
            $purchase->type === PhotoSlotPurchase::TYPE_EXTRA_PHOTO
                ? UserEventName::EXTRA_PHOTO_PURCHASED
                : UserEventName::EXTRA_ENTRY_PURCHASED,
            $purchase->user,
            properties: ['amount' => $purchase->amount],
            competitionId: $purchase->competition_id,
        );
    }
}
