<?php

namespace Tests\Feature;

use App\Console\Commands\PostSeedPhotos;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PhotoCompetitionReminderNotification;
use App\Notifications\PhotoCompetitionWonNotification;
use Database\Seeders\SeedUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PostSeedPhotosTest extends TestCase
{
    use RefreshDatabase;

    private int $nextUnsplashId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.unsplash.access_key' => 'test-key']);
        $this->seed(SeedUserSeeder::class);
    }

    private function fakeUnsplash(?array $ids = null): void
    {
        Http::fake([
            'api.unsplash.com/photos/random*' => function () use ($ids) {
                $ids ??= [
                    'unsplash-' . $this->nextUnsplashId++,
                    'unsplash-' . $this->nextUnsplashId++,
                ];

                return Http::response(array_map(fn(string $id) => $this->unsplashPhoto($id), $ids));
            },
            'api.unsplash.com/photos/*/download*' => Http::response(['url' => 'https://images.unsplash.com/x']),
        ]);
    }

    private function unsplashPhoto(string $id, ?string $description = 'Matatus on Moi Avenue at dusk'): array
    {
        return [
            'id' => $id,
            'width' => 4000,
            'height' => 6000,
            'description' => $description,
            'alt_description' => null,
            'urls' => ['raw' => "https://images.unsplash.com/photo-{$id}?ixid=abc"],
            'links' => ['download_location' => "https://api.unsplash.com/photos/{$id}/download?ixid=abc"],
            'user' => ['name' => 'Jane Mwangi', 'username' => 'janem'],
        ];
    }

    private function competition(array $attributes = []): PhotoCompetition
    {
        return PhotoCompetition::factory()->create(array_merge([
            'starts_at' => now()->startOfDay()->subDays(10),
            'ends_at' => now()->startOfDay()->addDays(4),
        ], $attributes));
    }

    private function travelToDue(PhotoCompetition $competition, int $index): void
    {
        $this->travelTo(PostSeedPhotos::dueAt($competition, $index)->copy()->addMinute());
    }

    public function test_seeder_creates_an_idempotent_pool_of_seed_users(): void
    {
        $this->seed(SeedUserSeeder::class);

        $this->assertSame(10, User::where('is_seed', true)->count());
        $this->assertSame(0, User::real()->count());
    }

    public function test_nothing_is_posted_before_the_first_photo_is_due(): void
    {
        $this->fakeUnsplash();
        $competition = $this->competition();
        $this->travelTo(PostSeedPhotos::dueAt($competition, 0)->copy()->subMinute());

        $this->artisan('app:post-seed-photos')->assertSuccessful();

        $this->assertSame(0, Photo::count());
        Http::assertNothingSent();
    }

    public function test_posts_a_hotlinked_unsplash_photo_credited_to_the_photographer(): void
    {
        $this->fakeUnsplash();
        $competition = $this->competition();
        $this->travelToDue($competition, 0);

        $this->artisan('app:post-seed-photos')->assertSuccessful();

        $photo = Photo::with('user')->sole();
        $this->assertTrue($photo->user->is_seed);
        $this->assertSame($competition->id, $photo->competition_id);
        $this->assertSame(Photo::DISK_REMOTE, $photo->disk);
        $this->assertSame('unsplash-1', $photo->unsplash_id);
        $this->assertStringStartsWith('https://images.unsplash.com/photo-unsplash-1?ixid=abc&w=2160', $photo->url);
        $this->assertStringStartsWith('https://images.unsplash.com/photo-unsplash-1?ixid=abc&w=1080', $photo->thumbnail_url);
        $this->assertSame(
            'Matatus on Moi Avenue at dusk. Photo by Jane Mwangi (@janem) on Unsplash',
            $photo->caption,
        );

        Http::assertSent(fn($request) => str_contains($request->url(), '/photos/unsplash-1/download')
            && $request->header('Authorization') === ['Client-ID test-key']);
    }

    public function test_posts_one_photo_per_day_for_three_days_from_the_same_seed_user(): void
    {
        $this->fakeUnsplash();
        $competition = $this->competition();

        $this->travelToDue($competition, 0);
        $this->artisan('app:post-seed-photos');
        $this->artisan('app:post-seed-photos');
        $this->assertSame(1, Photo::count());

        $this->travelToDue($competition, 1);
        $this->artisan('app:post-seed-photos');
        $this->assertSame(2, Photo::count());

        $this->travelToDue($competition, 2);
        $this->artisan('app:post-seed-photos');
        $this->travel(2)->days();
        $this->artisan('app:post-seed-photos');

        $this->assertSame(3, Photo::count());
        $this->assertSame(1, Photo::distinct()->count('user_id'));
        $this->assertSame(3, Photo::distinct()->count('unsplash_id'));
    }

    public function test_every_competition_is_posted_by_the_same_seed_user(): void
    {
        $this->fakeUnsplash();
        $first = $this->competition();
        $this->travelToDue($first, 0);
        $this->artisan('app:post-seed-photos');
        $firstPoster = Photo::sole()->user_id;

        $first->update(['status' => PhotoCompetition::STATUS_ENDED]);
        $second = $this->competition(['starts_at' => now()->startOfDay()->subDay()]);
        $this->travelToDue($second, 0);
        $this->artisan('app:post-seed-photos');

        $secondPoster = Photo::where('competition_id', $second->id)->sole()->user_id;
        $this->assertSame($firstPoster, $secondPoster);
    }

    public function test_the_seed_user_with_the_most_photos_is_featured(): void
    {
        $this->fakeUnsplash();
        $established = User::where('is_seed', true)->latest('created_at')->latest('id')->first();
        Photo::factory()->count(2)->create(['user_id' => $established->id]);
        $competition = $this->competition();
        $this->travelToDue($competition, 0);

        $this->artisan('app:post-seed-photos')->assertSuccessful();

        $this->assertSame($established->id, Photo::where('competition_id', $competition->id)->sole()->user_id);
    }

    public function test_skips_unsplash_photos_that_were_already_posted(): void
    {
        $this->fakeUnsplash(['already-used', 'brand-new']);
        $competition = $this->competition();
        Photo::factory()->create(['unsplash_id' => 'already-used']);
        $this->travelToDue($competition, 0);

        $this->artisan('app:post-seed-photos')->assertSuccessful();

        $this->assertTrue(Photo::where('competition_id', $competition->id)->where('unsplash_id', 'brand-new')->exists());
    }

    public function test_a_long_description_is_trimmed_but_the_credit_is_kept_whole(): void
    {
        $caption = PostSeedPhotos::caption($this->unsplashPhoto('x', str_repeat('Nairobi skyline ', 40)));

        $this->assertLessThanOrEqual(255, mb_strlen($caption));
        $this->assertStringEndsWith('... Photo by Jane Mwangi (@janem) on Unsplash', $caption);
    }

    public function test_seed_photos_can_win_and_their_prize_is_marked_paid(): void
    {
        Notification::fake();
        Setting::updateOrCreate(['key' => 'photo_prize_tiers'], ['value' => [500, 300]]);
        $competition = $this->competition();
        $seedUser = User::where('is_seed', true)->first();
        $seedPhoto = Photo::factory()->create(['competition_id' => $competition->id, 'user_id' => $seedUser->id, 'likes_count' => 50]);
        $realPhoto = Photo::factory()->create(['competition_id' => $competition->id, 'likes_count' => 5]);

        $this->artisan('app:end-photo-competition')->assertSuccessful();

        $seedWinner = PhotoCompetitionWinner::where('photo_id', $seedPhoto->id)->sole();
        $this->assertSame(1, $seedWinner->position);
        $this->assertNotNull($seedWinner->prize_paid_at);
        $this->assertNull($seedWinner->kopokopo_reference);
        $this->assertTrue($seedUser->fresh()->verified);

        // The real runner-up still waits for an admin-initiated payout.
        $realWinner = PhotoCompetitionWinner::where('photo_id', $realPhoto->id)->sole();
        $this->assertSame(2, $realWinner->position);
        $this->assertNull($realWinner->prize_paid_at);

        Notification::assertNotSentTo($seedUser, PhotoCompetitionWonNotification::class);
        Notification::assertSentTo($realPhoto->user, PhotoCompetitionWonNotification::class);
    }

    public function test_seed_users_do_not_get_the_mid_challenge_reminder(): void
    {
        Notification::fake();
        $realUser = User::factory()->create();
        $this->competition(['starts_at' => now()->subDays(2), 'ends_at' => now()->addDays(2)]);

        $this->artisan('app:send-photo-competition-reminder')->assertSuccessful();

        Notification::assertSentTo($realUser, PhotoCompetitionReminderNotification::class);
        Notification::assertNotSentTo(User::where('is_seed', true)->get(), PhotoCompetitionReminderNotification::class);
    }
}
