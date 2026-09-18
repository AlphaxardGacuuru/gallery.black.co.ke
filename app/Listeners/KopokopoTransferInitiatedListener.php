<?php

namespace App\Listeners;

use App\Events\KopokopoTransferInitiated;
use App\Models\User;
use App\Notifications\KopokopoTransferInitiatedNotification;

class KopokopoTransferInitiatedListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(KopokopoTransferInitiated $event): void
    {
        // Only notify if the recipient is a registered platform user —
        // transfers can also go to people with no Black Gallery account.
        $user = User::query()
            ->where('hashed_phone', hash('sha256', $event->phoneNumber))
            ->first();

        if ($user) {
            $user->notify(new KopokopoTransferInitiatedNotification($event));
        }
    }
}
