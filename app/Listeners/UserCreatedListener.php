<?php

namespace App\Listeners;

use App\Enums\UserEventName;
use App\Events\UserCreatedEvent;
use App\Jobs\ScreenNewSignupJob;
use App\Models\UserLogin;
use App\Services\EventRecorder;

class UserCreatedListener
{
    public function __construct(protected EventRecorder $eventRecorder) {}

    public function handle(UserCreatedEvent $event): void
    {
        $this->screenSignup($event);
        $this->recordEvent($event);
    }

    /**
     * Have the screening agent check for a fake or duplicate account.
     */
    private function screenSignup(UserCreatedEvent $event): void
    {
        ScreenNewSignupJob::dispatch($event->user, request()->ip());
    }

    /**
     * Only the social login callback has a {website} parameter, the same
     * check UserLoggedInListener uses.
     */
    private function recordEvent(UserCreatedEvent $event): void
    {
        $this->eventRecorder->record(
            UserEventName::SIGNED_UP,
            $event->user,
            source: request()->route()?->parameter('website') ?? UserLogin::METHOD_PASSWORD,
        );
    }
}
