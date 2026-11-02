<?php

namespace App\Listeners;

use App\Events\PhotoCompetitionEnded;
use App\Events\UserVerifiedEvent;
use App\Models\User;
use App\Notifications\PhotoCompetitionEndedNotification;
use App\Notifications\PhotoCompetitionWonNotification;

class PhotoCompetitionEndedListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PhotoCompetitionEnded $event): void
    {
        $this->verifyWinners($event);
        $this->notifyWinners($event);
        $this->notifyEntrants($event);
    }

    /**
     * Winning a competition earns the verified badge. It's permanent, so a
     * later loss doesn't take it away (admins can still revoke it manually).
     */
    private function verifyWinners(PhotoCompetitionEnded $event): void
    {
        $userIds = $event->competition->winners->pluck('user_id')->filter()->unique();

        if ($userIds->isEmpty()) {
            return;
        }

        // Fetched first so only users who weren't already verified hear
        // about it.
        $newlyVerified = User::query()
            ->whereIn('id', $userIds)
            ->where('verified', false)
            ->get();

        User::query()
            ->whereKey($newlyVerified->modelKeys())
            ->update(['verified' => true]);

        $newlyVerified->each(fn (User $user) => UserVerifiedEvent::dispatch($user));
    }

    private function notifyWinners(PhotoCompetitionEnded $event): void
    {
        $event->competition->winners
            // Seed accounts' emails are on a domain with no inbox.
            ->filter(fn($winner) => $winner->user && ! $winner->user->is_seed)
            ->each(fn($winner) => $winner->user->notify(
                new PhotoCompetitionWonNotification($event->competition, $winner)
            ));
    }

    private function notifyEntrants(PhotoCompetitionEnded $event): void
    {
        // Every entrant hears the challenge is over, winners included (they
        // also get their own placement notification from notifyWinners()).
        $event
            ->competition
            ->photos
            ->pluck('user')
            ->filter(fn($user) => $user && ! $user->is_seed)
            ->unique('id')
            ->each(fn($user) => $user->notify(
                new PhotoCompetitionEndedNotification($event->competition)
            ));
    }
}
