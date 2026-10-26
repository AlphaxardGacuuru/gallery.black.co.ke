<?php

namespace App\Listeners;

use App\Events\MpesaTransactionCreatedEvent;
use App\Events\PhotoPurchasedEvent;
use App\Models\PhotoPurchase;
use App\Notifications\MpesaTransactionCreatedNotification;

class MpesaTransactionCreatedListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(MpesaTransactionCreatedEvent $event)
    {
        $this->notifyPayer($event);
        $this->confirmPhotoPurchase($event);
    }

    private function notifyPayer(MpesaTransactionCreatedEvent $event): void
    {
        $event
            ->mpesaTransaction
            ->user
            ->notify(new MpesaTransactionCreatedNotification($event->mpesaTransaction));
    }

    /**
     * If this transaction's Kopokopo reference matches a pending photo
     * purchase, mark it paid. Transactions for anything else match nothing.
     * Only pending rows match, so a webhook Kopokopo delivers twice can't
     * pay the photographer twice.
     */
    private function confirmPhotoPurchase(MpesaTransactionCreatedEvent $event): void
    {
        $purchase = PhotoPurchase::query()
            ->where('kopokopo_reference', $event->mpesaTransaction->kopokopo_id)
            ->where('status', PhotoPurchase::STATUS_PENDING)
            ->first();

        if (! $purchase) {
            return;
        }

        $purchase->update([
            'status' => PhotoPurchase::STATUS_PAID,
            'mpesa_transaction_id' => $event->mpesaTransaction->id,
            'paid_at' => now(),
        ]);

        PhotoPurchasedEvent::dispatch($purchase);
    }
}
