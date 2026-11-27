<?php

namespace App\Services;

use App\Enums\UserEventName;
use App\Jobs\LinkAnonymousUserEventsJob;
use App\Jobs\RecordUserEventJob;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class EventRecorder
{
    /**
     * Record that $user (or, for a guest, the browser with $anonymousId) did
     * $event, for usage analytics. Seed accounts and admins are left out so
     * they don't skew the numbers. competition_id comes from $photo unless
     * given.
     */
    public function record(
        UserEventName $event,
        ?User $user,
        ?Photo $photo = null,
        ?string $source = null,
        array $properties = [],
        ?string $competitionId = null,
        ?string $anonymousId = null,
        ?string $path = null,
    ): void {
        if ($user?->is_seed || $user?->isAdmin() || (! $user && ! $anonymousId)) {
            return;
        }

        RecordUserEventJob::dispatch([
            'user_id' => $user?->id,
            'anonymous_id' => $anonymousId,
            'event' => $event->value,
            'photo_id' => $photo?->id,
            'competition_id' => $competitionId ?? $photo?->competition_id,
            'source' => $source,
            'path' => $path,
            'properties' => $properties ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Credit a guest's earlier events to $user once they sign in on the same
     * browser. Runs at most once a day per browser, since every event after
     * sign-in carries the same pair.
     */
    public function linkAnonymous(User $user, string $anonymousId): void
    {
        if (! Cache::add("user-events:linked:{$anonymousId}", true, now()->addDay())) {
            return;
        }

        LinkAnonymousUserEventsJob::dispatch($user->id, $anonymousId);
    }
}
