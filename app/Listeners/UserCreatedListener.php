<?php

namespace App\Listeners;

use App\Events\UserCreatedEvent;
use App\Jobs\ScreenNewSignupJob;

class UserCreatedListener
{
    public function handle(UserCreatedEvent $event): void
    {
        $this->screenSignup($event);
    }

    /**
     * Have the screening agent check for a fake or duplicate account.
     */
    private function screenSignup(UserCreatedEvent $event): void
    {
        ScreenNewSignupJob::dispatch($event->user, request()->ip());
    }
}
