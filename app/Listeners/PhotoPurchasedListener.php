<?php

namespace App\Listeners;

use App\Events\PhotoPurchasedEvent;
use App\Http\Services\KopokopoTransferService;
use App\Notifications\PhotoPurchasedNotification;
use App\Notifications\PhotoSoldNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class PhotoPurchasedListener
{
    public function __construct(protected KopokopoTransferService $kopokopoTransferService)
    {
        //
    }

    public function handle(PhotoPurchasedEvent $event): void
    {
        $event->purchase->loadMissing(['photo', 'buyer', 'seller']);

        // Pay first, so the seller's notification can say whether the money
        // is already on its way.
        $payoutStarted = $this->payPhotographer($event);

        $this->notifyBuyer($event);
        $this->notifySeller($event, $payoutStarted);
    }

    /**
     * Send the photographer their share straight away. This runs inside
     * Kopokopo's payment webhook, so a failure is logged rather than
     * thrown: the sale itself is already confirmed, and an admin can retry
     * the payout from the photo challenge page.
     */
    private function payPhotographer(PhotoPurchasedEvent $event): bool
    {
        try {
            [$status, $message] = $this->kopokopoTransferService->payPhotographer($event->purchase);
        } catch (Throwable $exception) {
            Log::error("Photo sale payout failed for purchase {$event->purchase->id}: {$exception->getMessage()}");

            return false;
        }

        if (! $status) {
            Log::warning("Photo sale payout not started for purchase {$event->purchase->id}: {$message}");
        }

        return $status;
    }

    private function notifyBuyer(PhotoPurchasedEvent $event): void
    {
        $event->purchase->buyer?->notify(new PhotoPurchasedNotification($event->purchase));
    }

    private function notifySeller(PhotoPurchasedEvent $event, bool $payoutStarted): void
    {
        $event->purchase->seller?->notify(new PhotoSoldNotification($event->purchase, $payoutStarted));
    }
}
