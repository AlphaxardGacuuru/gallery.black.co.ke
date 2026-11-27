<?php

namespace App\Listeners;

use App\Events\PhoneNumberRequestedEvent;
use App\Notifications\PhoneNumberRequestedNotification;

class PhoneNumberRequestedListener
{
    public function handle(PhoneNumberRequestedEvent $event): void
    {
        $this->notifyUser($event);
        $this->recordRequest($event);
    }

    private function notifyUser(PhoneNumberRequestedEvent $event): void
    {
        // Seed accounts' emails are on a domain with no inbox.
        if ($event->user->is_seed) {
            return;
        }

        $event->user->notify(new PhoneNumberRequestedNotification);
    }

    /**
     * Count the request for the admin users table. Seed accounts aren't
     * emailed, so nothing was sent to count.
     */
    private function recordRequest(PhoneNumberRequestedEvent $event): void
    {
        if ($event->user->is_seed) {
            return;
        }

        $event->user
            ->forceFill(['phone_request' => [
                'count' => $event->user->phone_requests_count + 1,
                'at' => now(),
            ]])
            ->save();
    }
}
