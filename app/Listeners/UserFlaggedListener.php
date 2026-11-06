<?php

namespace App\Listeners;

use App\Events\UserFlaggedEvent;
use App\Notifications\UserFlaggedNotification;

class UserFlaggedListener
{
    public function handle(UserFlaggedEvent $event): void
    {
        $this->notifyUser($event);
    }

    private function notifyUser(UserFlaggedEvent $event): void
    {
        // Seed accounts' emails are on a domain with no inbox.
        if ($event->user->is_seed) {
            return;
        }

        $event->user->notify(new UserFlaggedNotification);
    }
}
