<?php

namespace App\Listeners;

use App\Events\PhotoCompetitionEnded;
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
        $event->competition->winners
            ->filter(fn($winner) => $winner->user)
            ->each(fn($winner) => $winner->user->notify(
                new PhotoCompetitionWonNotification($event->competition, $winner)
            ));
    }
}
