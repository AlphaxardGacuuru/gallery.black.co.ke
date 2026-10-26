<?php

namespace App\Listeners;

use App\Events\MpesaPaymentFailedEvent;
use App\Models\PhotoPurchase;
use App\Models\PhotoSlotPurchase;

class MpesaPaymentFailedListener
{
    public function handle(MpesaPaymentFailedEvent $event): void
    {
        $this->failPendingSlotPurchase($event);
        $this->failPendingPhotoPurchase($event);
    }

    /**
     * Without this the buyer's "check your phone" prompt keeps spinning
     * until the purchase goes stale (PhotoSlotPurchase::PENDING_TTL_MINUTES),
     * even though Kopokopo has already said the payment won't happen.
     */
    private function failPendingSlotPurchase(MpesaPaymentFailedEvent $event): void
    {
        PhotoSlotPurchase::query()
            ->where('kopokopo_reference', $event->kopokopoReference)
            ->where('status', PhotoSlotPurchase::STATUS_PENDING)
            ->update(['status' => PhotoSlotPurchase::STATUS_FAILED]);
    }

    /**
     * Same as failPendingSlotPurchase(), for the buy-a-photo dialog.
     */
    private function failPendingPhotoPurchase(MpesaPaymentFailedEvent $event): void
    {
        PhotoPurchase::query()
            ->where('kopokopo_reference', $event->kopokopoReference)
            ->where('status', PhotoPurchase::STATUS_PENDING)
            ->update(['status' => PhotoPurchase::STATUS_FAILED]);
    }
}
