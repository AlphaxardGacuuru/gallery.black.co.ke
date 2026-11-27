<?php

namespace App\Listeners;

use App\Enums\UserEventName;
use App\Events\PhotoLikedEvent;
use App\Notifications\PhotoLikedNotification;
use App\Services\EventRecorder;

/**
 * Every side effect of a photo being liked (or, from the admin "manage
 * likes" tool, unliked) lives here, so new ones get added as another
 * method called from handle() rather than as another listener.
 */
class PhotoLikedListener
{
    public function __construct(protected EventRecorder $eventRecorder)
    {
        //
    }

    public function handle(PhotoLikedEvent $event): void
    {
        $this->notifyOwner($event);
        $this->recordEvent($event);
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

        if (! $owner || $owner->is_seed || $owner->is($event->likedBy)) {
            return;
        }

        $owner->notify(new PhotoLikedNotification($event->photo, $event->likedBy));
    }

    /**
     * Only likes the user gave themselves. An admin's "manage likes" toggle
     * and seed likes aren't the user's own action.
     */
    protected function recordEvent(PhotoLikedEvent $event): void
    {
        if (! $event->liked || in_array($event->source, [PhotoLikedEvent::SOURCE_ADMIN, PhotoLikedEvent::SOURCE_SEED], true)) {
            return;
        }

        $this->eventRecorder->record(
            UserEventName::PHOTO_LIKED,
            $event->likedBy,
            photo: $event->photo,
            source: $event->source,
        );
    }
}
