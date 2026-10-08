<?php

namespace App\Listeners;

use App\Events\PhotoLikedEvent;
use App\Notifications\PhotoLikedNotification;

/**
 * Every side effect of a photo being liked (or, from the admin "manage
 * likes" tool, unliked) lives here, so new ones get added as another
 * method called from handle() rather than as another listener.
 */
class PhotoLikedListener
{
    public function __construct()
    {
        //
    }

    public function handle(PhotoLikedEvent $event): void
    {
        $this->notifyOwner($event);
    }

    /**
     * Tell the photo's owner someone liked it. Admin "manage likes" toggling
     * can remove a like too, and this event fires either way (see
     * AdminPhotoLikeController::toggle), so only a genuine new like counts.
     */
    protected function notifyOwner(PhotoLikedEvent $event): void
    {
        if (! $event->liked) {
            return;
        }

        $owner = $event->photo->user;

        if (! $owner || $owner->is($event->likedBy)) {
            return;
        }

        $owner->notify(new PhotoLikedNotification($event->photo, $event->likedBy));
    }
}
