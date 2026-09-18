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
        $winner = $event->competition->winnerPhoto;

        if ($winner) {
            $winner
                ->user
                ->notify(new PhotoCompetitionWonNotification($event->competition));
        }
    }
}
