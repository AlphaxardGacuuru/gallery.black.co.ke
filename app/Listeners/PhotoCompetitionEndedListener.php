<?php

namespace App\Listeners;

use App\Enums\UserEventName;
use App\Enums\VerificationReason;
use App\Events\PhotoCompetitionEnded;
use App\Events\UserVerifiedEvent;
use App\Models\User;
use App\Notifications\PhotoCompetitionEndedNotification;
use App\Notifications\PhotoCompetitionWonNotification;
use App\Services\EventRecorder;

class PhotoCompetitionEndedListener
{
    /**
     * Create the event listener.
     */
    public function __construct(protected EventRecorder $eventRecorder)
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
        $this->recordPrizesWon($event);
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

        // Only users who weren't already verified hear about it.
        User::query()
            ->whereIn('id', $userIds)
            ->verified(false)
            ->get()
            ->each(function (User $user) {
                $user->markVerified(VerificationReason::COMPETITION_WINNER);

                UserVerifiedEvent::dispatch($user);
            });
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

    private function recordPrizesWon(PhotoCompetitionEnded $event): void
    {
        $event->competition
            ->winners
            ->loadMissing(['user', 'photo'])
            ->filter(fn ($winner) => $winner->user)
            ->each(fn ($winner) => $this->eventRecorder->record(
                UserEventName::PRIZE_WON,
                $winner->user,
                photo: $winner->photo,
                properties: ['position' => $winner->position, 'amount' => $winner->prize_amount],
                competitionId: $event->competition->id,
            ));
    }
}
