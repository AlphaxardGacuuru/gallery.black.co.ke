<?php

namespace Tests\Feature;

use App\Console\Commands\PostSeedLikes;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\User;
use App\Notifications\PhotoLikedNotification;
use Database\Seeders\SeedUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PostSeedLikesTest extends TestCase
{
    use RefreshDatabase;

    private PhotoCompetition $competition;

    private Photo $realPhoto;

    private Photo $seedPhoto;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Http::fake(['api.unsplash.com/photos/*/download' => Http::response()]);
        $this->seed(SeedUserSeeder::class);

        $this->competition = PhotoCompetition::factory()->create([
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->startOfDay()->addDays(4),
        ]);

        $this->travelTo($this->competition->starts_at);

        $this->realPhoto = Photo::factory()->create([
            'competition_id' => $this->competition->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->seedPhoto = Photo::factory()->create([
            'competition_id' => $this->competition->id,
            'user_id' => User::query()
                ->where('is_seed', true)
                ->first()
                ->id,
        ]);
    }

    private function seedLikes(Photo $photo): int
    {
        return $photo->likes()
            ->whereHas('user', fn($query) => $query->where('is_seed', true))
            ->count();
    }

    public function test_nothing_is_liked_before_the_first_like_is_due(): void
    {
        $this->travelTo(PostSeedLikes::dueAt($this->competition, $this->realPhoto, 0)->subSecond());

        $this->artisan('app:post-seed-likes')->assertSuccessful();

        $this->assertSame(0, $this->seedLikes($this->realPhoto));
    }

    public function test_photos_get_their_full_seed_likes_by_the_end_of_the_competition(): void
    {
        $this->travelTo($this->competition->ends_at->copy()->subSecond());

        $this->artisan('app:post-seed-likes')->assertSuccessful();
        $this->artisan('app:post-seed-likes')->assertSuccessful();

        $this->assertSame(PostSeedLikes::LIKES_PER_PHOTO, $this->seedLikes($this->realPhoto));
        $this->assertSame(PostSeedLikes::LIKES_PER_SEED_PHOTO, $this->seedLikes($this->seedPhoto));
        $this->assertSame(PostSeedLikes::LIKES_PER_PHOTO, $this->realPhoto->fresh()->likes_count);
        $this->assertSame(PostSeedLikes::LIKES_PER_SEED_PHOTO, $this->seedPhoto->fresh()->likes_count);
        $this->assertFalse(
            $this->seedPhoto
                ->likes()
                ->where('user_id', $this->seedPhoto->user_id)
                ->exists()
        );
    }

    public function test_likes_are_spread_across_the_competition(): void
    {
        $dueTimes = collect(range(0, PostSeedLikes::LIKES_PER_PHOTO - 1))
            ->map(fn(int $index) => PostSeedLikes::dueAt($this->competition, $this->realPhoto, $index));

        $this->assertTrue($dueTimes->every(fn($dueAt, int $index) => $dueAt->between(
            $this->competition->starts_at->copy()->addDays($index * 4 / PostSeedLikes::LIKES_PER_PHOTO),
            $this->competition->starts_at->copy()->addDays(($index + 1) * 4 / PostSeedLikes::LIKES_PER_PHOTO),
        )));

        $this->travelTo($dueTimes[2]->copy()->addSecond());
        $this->artisan('app:post-seed-likes')->assertSuccessful();

        $this->assertSame(3, $this->seedLikes($this->realPhoto));
    }

    public function test_a_photo_posted_late_still_gets_all_its_likes_before_the_end(): void
    {
        $this->travelTo($this->competition->ends_at->copy()->subDay());
        $latePhoto = Photo::factory()->create(['competition_id' => $this->competition->id]);

        $this->assertTrue(PostSeedLikes::dueAt($this->competition, $latePhoto, 0)->greaterThanOrEqualTo(now()));

        $this->travelTo($this->competition->ends_at->copy()->subSecond());
        $this->artisan('app:post-seed-likes')->assertSuccessful();

        $this->assertSame(PostSeedLikes::LIKES_PER_PHOTO, $this->seedLikes($latePhoto));
    }

    public function test_real_owners_are_notified_but_seed_owners_are_not(): void
    {
        $this->travelTo($this->competition->ends_at->copy()->subSecond());

        $this->artisan('app:post-seed-likes')->assertSuccessful();

        Notification::assertSentToTimes($this->realPhoto->user, PhotoLikedNotification::class, PostSeedLikes::LIKES_PER_PHOTO);
        Notification::assertNotSentTo($this->seedPhoto->user, PhotoLikedNotification::class);
    }

    public function test_ended_competitions_get_no_likes(): void
    {
        $this->competition->update(['status' => PhotoCompetition::STATUS_ENDED]);
        $this->travelTo($this->competition->ends_at->copy()->subSecond());

        $this->artisan('app:post-seed-likes')->assertSuccessful();

        $this->assertSame(0, $this->seedLikes($this->realPhoto));
    }
}
