<?php

namespace App\Listeners;

use App\Enums\UserEventName;
use App\Events\UserVerifiedEvent;
use App\Notifications\UserVerifiedNotification;
use App\Services\EventRecorder;

class UserVerifiedListener
{
    public function __construct(protected EventRecorder $eventRecorder) {}

    public function handle(UserVerifiedEvent $event): void
    {
        $this->notifyUser($event);
        $this->recordEvent($event);
    }

    private function notifyUser(UserVerifiedEvent $event): void
    {
        // Seed accounts' emails are on a domain with no inbox.
        if ($event->user->is_seed) {
            return;
        }

        $event->user->notify(new UserVerifiedNotification);
    }

    private function recordEvent(UserVerifiedEvent $event): void
    {
        $this->eventRecorder->record(
            UserEventName::VERIFIED,
            $event->user,
            properties: ['reason' => $event->user->verification_reason?->value],
        );
    }
}
