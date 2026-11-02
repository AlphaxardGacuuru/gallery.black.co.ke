<?php

namespace App\Listeners;

use App\Events\UserVerifiedEvent;
use App\Notifications\UserVerifiedNotification;

class UserVerifiedListener
{
    public function handle(UserVerifiedEvent $event): void
    {
        $this->notifyUser($event);
    }

    private function notifyUser(UserVerifiedEvent $event): void
    {
        // Seed accounts' emails are on a domain with no inbox.
        if ($event->user->is_seed) {
            return;
        }

        $event->user->notify(new UserVerifiedNotification);
    }
}
