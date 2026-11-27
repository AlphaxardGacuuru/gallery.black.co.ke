<?php

namespace App\Listeners;

use App\Enums\UserEventName;
use App\Events\UserFlaggedEvent;
use App\Notifications\PhoneNumberRequiredNotification;
use App\Notifications\UserFlaggedNotification;
use App\Services\EventRecorder;

class UserFlaggedListener
{
    public function __construct(protected EventRecorder $eventRecorder) {}

    public function handle(UserFlaggedEvent $event): void
    {
        $this->notifyUser($event);
        $this->recordEvent($event);
    }

    /**
     * A screening flag only asks for a phone number, so it gets a gentler
     * message than an admin's flag. Changing phone number flags the user
     * while they're on the profile page, which already asks them to pay
     * from the new number, so that one isn't sent anywhere.
     */
    private function notifyUser(UserFlaggedEvent $event): void
    {
        // Seed accounts' emails are on a domain with no inbox.
        if ($event->user->is_seed || $event->user->isFlaggedUntilPhoneVerified()) {
            return;
        }

        $event->user->notify(
            $event->user->isFlaggedUntilPhone()
                ? new PhoneNumberRequiredNotification
                : new UserFlaggedNotification
        );
    }

    private function recordEvent(UserFlaggedEvent $event): void
    {
        $this->eventRecorder->record(
            UserEventName::FLAGGED,
            $event->user,
            source: $event->user->flag_source,
        );
    }
}
