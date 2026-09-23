<?php

namespace Tests\Feature;

use App\Events\PhotoCompetitionStarted;
use App\Models\PhotoCompetition;
use App\Models\Setting;
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

    public function test_current_endpoint_exposes_top_prize_amount_from_settings(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'photo_prize_tiers'],
            ['value' => [750, 250]]
        );

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/photos/current');

        $response->assertOk()->assertJsonPath('topPrizeAmount', 750);
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

    public function test_end_command_creates_top_ranked_winner_rows(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'photo_prize_tiers'],
            ['value' => [500, 300, 100]]
        );

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();

        $fourthPlace = $competition->photos()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'public',
            'path' => 'photos/fourth.jpg',
            'likes_count' => 1,
        ]);
        $thirdPlace = $competition->photos()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'public',
            'path' => 'photos/third.jpg',
            'likes_count' => 3,
        ]);
        $secondPlace = $competition->photos()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'public',
            'path' => 'photos/second.jpg',
            'likes_count' => 4,
        ]);
        $firstPlace = $competition->photos()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'public',
            'path' => 'photos/first.jpg',
            'likes_count' => 5,
        ]);

        $this->artisan('app:end-photo-competition')->assertSuccessful();

        $competition->refresh();
        $this->assertSame(PhotoCompetition::STATUS_ENDED, $competition->status);
        $this->assertCount(3, $competition->winners);

        $winners = $competition->winners;
        $this->assertSame($firstPlace->id, $winners[0]->photo_id);
        $this->assertSame($firstPlace->user_id, $winners[0]->user_id);
        $this->assertSame(1, $winners[0]->position);
        $this->assertSame(500, $winners[0]->prize_amount);

        $this->assertSame($secondPlace->id, $winners[1]->photo_id);
        $this->assertSame(2, $winners[1]->position);
        $this->assertSame(300, $winners[1]->prize_amount);

        $this->assertSame($thirdPlace->id, $winners[2]->photo_id);
        $this->assertSame(3, $winners[2]->position);
        $this->assertSame(100, $winners[2]->prize_amount);

        $this->assertFalse($competition->winners->pluck('photo_id')->contains($fourthPlace->id));
    }

    public function test_end_command_stops_at_first_zero_tier(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'photo_prize_tiers'],
            ['value' => [500, 0, 100]]
        );

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();

        foreach (range(1, 3) as $i) {
            $competition->photos()->create([
                'user_id' => User::factory()->create()->id,
                'disk' => 'public',
                'path' => "photos/{$i}.jpg",
                'likes_count' => $i,
            ]);
        }

        $this->artisan('app:end-photo-competition');

        $competition->refresh();
        $this->assertCount(1, $competition->winners);
        $this->assertSame(1, $competition->winners->first()->position);
    }

    public function test_end_command_defaults_to_position_one_only_when_tiers_setting_absent(): void
    {
        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();

        $competition->photos()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'public',
            'path' => 'photos/only.jpg',
            'likes_count' => 5,
        ]);

        $this->artisan('app:end-photo-competition');

        $competition->refresh();
        $this->assertCount(1, $competition->winners);
        $this->assertSame(500, $competition->winners->first()->prize_amount);
    }

    public function test_end_command_creates_no_winners_when_no_photos_submitted(): void
    {
        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();

        $this->artisan('app:end-photo-competition')->assertSuccessful();

        $competition->refresh();
        $this->assertSame(PhotoCompetition::STATUS_ENDED, $competition->status);
        $this->assertCount(0, $competition->winners);
    }

    public function test_current_endpoint_returns_winner_photos_after_competition_ends(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'photo_prize_tiers'],
            ['value' => [500, 300]]
        );

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();

        $secondPlace = $competition->photos()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'public',
            'path' => 'photos/second.jpg',
            'likes_count' => 3,
        ]);
        $firstPlace = $competition->photos()->create([
            'user_id' => User::factory()->create()->id,
            'disk' => 'public',
            'path' => 'photos/first.jpg',
            'likes_count' => 5,
        ]);

        $this->artisan('app:end-photo-competition');

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/photos/current');

        $response->assertOk()
            ->assertJsonPath('data.status', PhotoCompetition::STATUS_ENDED)
            ->assertJsonPath('data.photos.0.id', $firstPlace->id)
            ->assertJsonPath('data.photos.0.isWinner', true)
            ->assertJsonPath('data.photos.0.position', 1)
            ->assertJsonPath('data.photos.1.id', $secondPlace->id)
            ->assertJsonPath('data.photos.1.isWinner', false)
            ->assertJsonPath('data.photos.1.position', 2)
            ->assertJsonCount(2, 'data.photos')
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
