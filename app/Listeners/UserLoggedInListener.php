<?php

namespace App\Listeners;

use App\Enums\UserEventName;
use App\Models\User;
use App\Models\UserLogin;
use App\Services\EventRecorder;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

/**
 * Every way in (Google, the admin password form and its two-factor step)
 * ends in Auth::login(), which fires Login, so they're all recorded here.
 */
class UserLoggedInListener
{
    public function __construct(protected EventRecorder $eventRecorder)
    {
        //
    }

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->recordLogin($event->user);

        $event->user->markActive();
    }

    /**
     * Only the social login callback has a {website} parameter, so a
     * login without one came through the admin password form.
     */
    private function recordLogin(User $user): void
    {
        $request = request();
        $method = $request->route()?->parameter('website') ?? UserLogin::METHOD_PASSWORD;

        $user->logins()
            ->create([
                'method' => $method,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            ]);

        $this->eventRecorder->record(UserEventName::LOGGED_IN, $user, source: $method);
    }
}
