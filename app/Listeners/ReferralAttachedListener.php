<?php

namespace App\Listeners;

use App\Enums\UserEventName;
use App\Events\ReferralAttachedEvent;
use App\Models\Referral;
use App\Notifications\ReferralSignupNotification;
use App\Services\EventRecorder;

/**
 * Every side effect of an admin crediting a user to a referrer lives here,
 * so new ones get added as another method called from handle() rather
 * than as another listener.
 */
class ReferralAttachedListener
{
    public function __construct(protected EventRecorder $eventRecorder)
    {
        //
    }

    public function handle(ReferralAttachedEvent $event): void
    {
        $this->notifyReferrer($event);
        $this->recordEvent($event);
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

    protected function recordEvent(ReferralAttachedEvent $event): void
    {
        if (! $event->referral->referred) {
            return;
        }

        $this->eventRecorder->record(
            UserEventName::REFERRED,
            $event->referral->referred,
            source: Referral::SOURCE_ADMIN,
            properties: ['referrer_id' => $event->referral->referrer_id],
        );
    }
}
