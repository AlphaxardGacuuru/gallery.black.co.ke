<?php

namespace Tests\Feature;

use App\Events\PhotoCompetitionStarted;
use App\Models\PhotoCompetition;
use App\Models\User;
use App\Notifications\PhotoCompetitionStartedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoCompetitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_command_creates_active_competition(): void
    {
        Event::fake([PhotoCompetitionStarted::class]);

        $this->artisan('app:start-photo-competition')->assertSuccessful();

        $this->assertDatabaseCount('photo_competitions', 1);
        $competition = PhotoCompetition::first();
        $this->assertSame(PhotoCompetition::STATUS_ACTIVE, $competition->status);

        Event::assertDispatched(
            PhotoCompetitionStarted::class,
            fn(PhotoCompetitionStarted $event): bool => $event->competition->is($competition),
        );
    }

    public function test_start_event_notifies_only_users_with_push_subscriptions(): void
    {
        Notification::fake();

        $subscribedUser = User::factory()->create();
        $subscribedUser->updatePushSubscription('https://example.com/push/subscribed');
        $unsubscribedUser = User::factory()->create();
        $competition = PhotoCompetition::factory()->create();

        PhotoCompetitionStarted::dispatch($competition);

        Notification::assertSentTo($subscribedUser, PhotoCompetitionStartedNotification::class);
        Notification::assertNotSentTo($unsubscribedUser, PhotoCompetitionStartedNotification::class);
    }

    public function test_current_endpoint_returns_active_competition_with_photos(): void
    {
        $this->artisan('app:start-photo-competition');

        $competition = PhotoCompetition::first();
        $user = User::factory()->create();
        $photo = $competition->photos()->create([
            'user_id' => $user->id,
            'disk' => 'public',
            'path' => 'photos/test.jpg',
        ]);

        $response = $this->getJson('/api/photos/current');

        $response->assertOk()
            ->assertJsonPath('data.id', $competition->id)
            ->assertJsonPath('data.photos.0.id', $photo->id);
    }

    public function test_authenticated_user_can_like_and_unlike_a_photo(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $author = User::factory()->create();
        $viewer = User::factory()->create();

        $photo = $competition->photos()->create([
            'user_id' => $author->id,
            'disk' => 'public',
            'path' => 'photos/test.jpg',
        ]);

        $this->actingAs($viewer)
            ->postJson("/api/photos/{$photo->id}/like")
            ->assertOk()
            ->assertJsonPath('data.likesCount', 1)
            ->assertJsonPath('data.isLikedByViewer', true);

        $this->assertSame(1, $photo->fresh()->likes_count);

        $this->actingAs($viewer)
            ->postJson("/api/photos/{$photo->id}/like")
            ->assertOk()
            ->assertJsonPath('data.likesCount', 0)
            ->assertJsonPath('data.isLikedByViewer', false);

        $this->assertSame(0, $photo->fresh()->likes_count);
    }

    public function test_end_command_crowns_the_most_liked_photo_as_winner(): void
    {
        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();

        $competition->photos()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'public',
            'path' => 'photos/losing.jpg',
            'likes_count' => 1,
        ]);

        $winningPhoto = $competition->photos()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'public',
            'path' => 'photos/winning.jpg',
            'likes_count' => 5,
        ]);

        $this->artisan('app:end-photo-competition')->assertSuccessful();

        $competition->refresh();
        $this->assertSame(PhotoCompetition::STATUS_ENDED, $competition->status);
        $this->assertSame($winningPhoto->id, $competition->winner_photo_id);
    }

    public function test_current_endpoint_returns_winner_photo_after_competition_ends(): void
    {
        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();

        $winningPhoto = $competition->photos()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'public',
            'path' => 'photos/winning.jpg',
            'likes_count' => 5,
        ]);

        $this->artisan('app:end-photo-competition');

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/photos/current');

        $response->assertOk()
            ->assertJsonPath('data.status', PhotoCompetition::STATUS_ENDED)
            ->assertJsonPath('data.photos.0.id', $winningPhoto->id)
            ->assertJsonPath('data.photos.0.isWinner', true)
            ->assertJsonCount(1, 'data.photos')
            ->assertJsonPath('nextStartsAt', fn($value) => $value !== null);
    }

    public function test_discover_endpoint_only_returns_photos_from_ended_competitions(): void
    {
        $this->artisan('app:start-photo-competition');
        $activeCompetition = PhotoCompetition::first();
        $author = User::factory()->create();

        $activeCompetition->photos()->create([
            'user_id' => $author->id,
            'disk' => 'public',
            'path' => 'photos/active.jpg',
        ]);

        $endedCompetition = PhotoCompetition::create([
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subWeek()->addDays(4),
            'status' => PhotoCompetition::STATUS_ENDED,
            'prize_amount' => 500,
        ]);
        $endedPhoto = $endedCompetition->photos()->create([
            'user_id' => $author->id,
            'disk' => 'public',
            'path' => 'photos/ended.jpg',
        ]);

        $response = $this->getJson('/api/photos/discover');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $endedPhoto->id);
    }
}
