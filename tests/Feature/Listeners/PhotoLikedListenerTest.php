<?php

namespace Tests\Feature\Listeners;

use App\Events\PhotoLikedEvent;
use App\Listeners\PhotoLikedListener;
use App\Models\Photo;
use App\Models\User;
use App\Notifications\PhotoLikedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PhotoLikedListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_is_notified_when_someone_likes_their_photo(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $owner->id]);

        (new PhotoLikedListener)->handle(new PhotoLikedEvent($photo, $liker, true));

        Notification::assertSentTo($owner, PhotoLikedNotification::class);
    }

    public function test_the_owner_is_not_notified_when_they_like_their_own_photo(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $owner->id]);

        (new PhotoLikedListener)->handle(new PhotoLikedEvent($photo, $owner, true));

        Notification::assertNothingSentTo($owner);
    }

    public function test_the_owner_is_not_notified_when_a_like_is_removed(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $owner->id]);

        (new PhotoLikedListener)->handle(new PhotoLikedEvent($photo, $liker, false));

        Notification::assertNothingSentTo($owner);
    }
}
