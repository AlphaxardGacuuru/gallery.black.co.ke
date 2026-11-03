<?php

namespace App\Listeners;

use App\Events\PhoneNumberRequestedEvent;
use App\Notifications\PhoneNumberRequestedNotification;

class PhoneNumberRequestedListener
{
    public function handle(PhoneNumberRequestedEvent $event): void
    {
        $this->notifyUser($event);
    }

    private function notifyUser(PhoneNumberRequestedEvent $event): void
    {
        // Seed accounts' emails are on a domain with no inbox.
        if ($event->user->is_seed) {
            return;
        }

        $event->user->notify(new PhoneNumberRequestedNotification);
    }
}
