<?php

namespace App\Jobs;

use App\Models\UserEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gives a guest's events (pages they opened before signing in) to the
 * account they signed in to, so a visit can be followed through to sign-up.
 */
class LinkAnonymousUserEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $userId, public string $anonymousId) {}

    public function handle(): void
    {
        UserEvent::query()
            ->where('anonymous_id', $this->anonymousId)
            ->whereNull('user_id')
            ->update(['user_id' => $this->userId]);
    }
}
