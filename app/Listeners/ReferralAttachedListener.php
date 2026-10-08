<?php

namespace App\Listeners;

use App\Events\ReferralAttachedEvent;
use App\Notifications\ReferralSignupNotification;

/**
 * Every side effect of an admin crediting a user to a referrer lives here,
 * so new ones get added as another method called from handle() rather
 * than as another listener.
 */
class ReferralAttachedListener
{
    public function __construct()
    {
        //
    }

    public function handle(ReferralAttachedEvent $event): void
    {
        $this->notifyReferrer($event);
    }

    /**
     * Tell the referrer they've been credited, with the same notification a
     * signup through their referral link sends.
     */
    protected function notifyReferrer(ReferralAttachedEvent $event): void
    {
        $referred = $event->referral->referred;

        if ($referred) {
            $event
                ->referral
                ->referrer
                ?->notify(new ReferralSignupNotification($referred));
        }
    }
}
