<?php

namespace App\Listeners;

use App\Events\MpesaTransactionCreatedEvent;
use App\Enums\UserEventName;
use App\Events\PhotoPurchasedEvent;
use App\Http\Services\UserService;
use App\Models\PhoneVerification;
use App\Models\PhotoPurchase;
use App\Notifications\MpesaTransactionCreatedNotification;
use App\Services\EventRecorder;

class MpesaTransactionCreatedListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(
        protected UserService $userService,
        protected EventRecorder $eventRecorder,
    ) {
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
        $this->confirmPhoneVerification($event);
    }

    private function notifyPayer(MpesaTransactionCreatedEvent $event): void
    {
        $event
            ->mpesaTransaction
            ->user
            ->notify(new MpesaTransactionCreatedNotification($event->mpesaTransaction));
    }

    /**
     * If this transaction's Kopokopo reference matches a pending (or
     * expired, for a payment that landed late) photo purchase, mark it
     * paid. Transactions for anything else match nothing. Paid rows never
     * match again, so a webhook Kopokopo delivers twice can't pay the
     * photographer twice.
     */
    private function confirmPhotoPurchase(MpesaTransactionCreatedEvent $event): void
    {
        $purchase = PhotoPurchase::query()
            ->where('kopokopo_reference', $event->mpesaTransaction->kopokopo_id)
            ->whereIn('status', PhotoPurchase::UNSETTLED_STATUSES)
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

    /**
     * If this transaction pays a phone verification, lift the flag that
     * changing the number set. Only when the number that paid is still the
     * one on the profile: someone who changed it again since has to
     * confirm the newer one.
     */
    private function confirmPhoneVerification(MpesaTransactionCreatedEvent $event): void
    {
        $verification = PhoneVerification::query()
            ->with('user')
            ->where('kopokopo_reference', $event->mpesaTransaction->kopokopo_id)
            ->whereIn('status', PhoneVerification::UNSETTLED_STATUSES)
            ->first();

        if (! $verification) {
            return;
        }

        $verification->update([
            'status' => PhoneVerification::STATUS_PAID,
            'mpesa_transaction_id' => $event->mpesaTransaction->id,
            'paid_at' => now(),
        ]);

        $user = $verification->user;

        if (! $user?->isFlaggedUntilPhoneVerified() || $user->phone !== $verification->phone) {
            return;
        }

        $this->userService->unflag($user);

        $this->eventRecorder->record(
            UserEventName::PHONE_VERIFIED,
            $user,
            properties: ['amount' => $verification->amount],
        );
    }
}
