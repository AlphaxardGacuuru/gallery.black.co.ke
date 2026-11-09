<?php

namespace Tests\Feature;

use App\Console\Commands\PostSeedPhotos;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoImage;
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
        Http::fake(['api.unsplash.com/photos/*/download' => Http::response()]);
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

        $this->assertSame(100, User::where('is_seed', true)->count());
        $this->assertSame(0, User::real()->count());
    }

    public function test_seeder_gives_seed_users_their_avatar_without_overwriting_a_changed_one(): void
    {
        $chebet = User::firstWhere('email', SeedUserSeeder::email('Chebet Jepkosgei'));
        $mutua = User::firstWhere('email', SeedUserSeeder::email('Mutua Musyoka'));

        $this->assertSame(SeedUserSeeder::avatarUrl('Chebet Jepkosgei'), $chebet->avatar);
        $this->assertSame(100, User::where('avatar', 'LIKE', 'https://images.unsplash.com/%')->count());
        Http::assertSent(fn ($request) => $request->url() === 'https://api.unsplash.com/photos/' . SeedUserSeeder::AVATARS['Chebet Jepkosgei'][0] . '/download');

        $mutua->update(['avatar' => 'avatars/changed.png']);

        $this->seed(SeedUserSeeder::class);

        $this->assertSame('/storage/avatars/changed.png', $mutua->fresh()->avatar);
    }

    public function test_nothing_is_posted_before_the_first_photo_is_due(): void
    {
        $this->fakeUnsplash();
        $competition = $this->competition();
        $firstDue = collect(range(0, PostSeedPhotos::posterCount() - 1))
            ->map(fn(int $position) => PostSeedPhotos::dueAt($competition, 0, $position))
            ->min();
        $this->travelTo($firstDue->copy()->subMinute());

        $this->artisan('app:post-seed-photos')->assertSuccessful();

        $this->assertSame(0, Photo::count());
        Http::assertNothingSent();
    }

    public function test_posts_two_hotlinked_unsplash_photos_credited_to_the_photographer(): void
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
            'Matatus on Moi Avenue at dusk. Photos by Jane Mwangi (@janem) on Unsplash',
            $photo->caption,
        );

        $image = PhotoImage::where('photo_id', $photo->id)->sole();
        $this->assertSame('unsplash-2', $image->unsplash_id);
        $this->assertSame(1, $image->position);
        $this->assertStringStartsWith('https://images.unsplash.com/photo-unsplash-2?ixid=abc&w=2160', $image->url);
        $this->assertStringStartsWith('https://images.unsplash.com/photo-unsplash-2?ixid=abc&w=1080', $image->thumbnail_url);

        Http::assertSent(fn($request) => str_contains($request->url(), '/photos/unsplash-1/download')
            && $request->header('Authorization') === ['Client-ID test-key']);
        Http::assertSent(fn($request) => str_contains($request->url(), '/photos/unsplash-2/download'));
    }

    public function test_photos_by_different_photographers_credit_each_one(): void
    {
        $other = array_merge($this->unsplashPhoto('y'), ['user' => ['name' => 'Otieno Ouma', 'username' => 'otieno']]);

        $this->assertSame(
            'Matatus on Moi Avenue at dusk. Photos by Jane Mwangi (@janem) and Otieno Ouma (@otieno) on Unsplash',
            PostSeedPhotos::caption($this->unsplashPhoto('x'), $other),
        );
    }

    public function test_an_unsplash_photo_used_as_a_second_image_is_not_posted_again(): void
    {
        $this->fakeUnsplash(['used-as-image', 'fresh-1', 'fresh-2']);
        $competition = $this->competition();
        PhotoImage::factory()->create(['unsplash_id' => 'used-as-image']);
        $this->travelToDue($competition, 0);

        $this->artisan('app:post-seed-photos')->assertSuccessful();

        $photo = Photo::where('competition_id', $competition->id)->sole();
        $this->assertSame('fresh-1', $photo->unsplash_id);
        $this->assertSame('fresh-2', PhotoImage::where('photo_id', $photo->id)->sole()->unsplash_id);
    }

    public function test_each_poster_posts_one_photo_a_day_for_three_days(): void
    {
        $this->fakeUnsplash();
        $competition = $this->competition([
            'starts_at' => now()->startOfDay(),
            'ends_at' => now()->startOfDay()->addDays(4),
        ]);

        $this->travelTo(PostSeedPhotos::dueAt($competition, 0)->copy()->addMinute());
        $this->artisan('app:post-seed-photos');
        $this->assertSame(1, Photo::count());

        // Run like the scheduler would, every ten minutes until the end.
        while (now()->lessThan($competition->ends_at)) {
            $this->artisan('app:post-seed-photos');
            $this->travel(10)->minutes();
        }

        $posters = PostSeedPhotos::posters();
        $this->assertCount(PostSeedPhotos::posterCount(), $posters);
        $this->assertSame(PostSeedPhotos::posterCount() * PostSeedPhotos::PHOTOS_PER_COMPETITION, Photo::count());
        $this->assertSame(Photo::count(), Photo::distinct()->count('unsplash_id'));

        $posters->each(fn(User $poster) => $this->assertSame(
            PostSeedPhotos::PHOTOS_PER_COMPETITION,
            Photo::query()
                ->where('user_id', $poster->id)
                ->get()
                ->map(fn(Photo $photo) => $photo->created_at->toDateString())
                ->unique()
                ->count()
        ));
    }

    public function test_the_posters_are_always_the_first_seed_users_in_the_list(): void
    {
        // Photo history and creation order don't matter, only list order.
        $busy = User::query()
            ->where('email', SeedUserSeeder::email(SeedUserSeeder::NAMES[10][0]))
            ->sole();
        Photo::factory()->count(5)->create(['user_id' => $busy->id]);

        $this->assertSame(
            collect(SeedUserSeeder::NAMES)
                ->take(PostSeedPhotos::posterCount())
                ->map(fn(array $entry) => SeedUserSeeder::email($entry[0]))
                ->all(),
            PostSeedPhotos::posters()
                ->pluck('email')
                ->all()
        );
        $this->assertSame(
            ['mutua.musyoka@seed.black.co.ke', 'chebet.jepkosgei@seed.black.co.ke'],
            PostSeedPhotos::posters()
                ->take(2)
                ->pluck('email')
                ->all()
        );
    }

    public function test_changing_the_number_of_posters_keeps_the_same_users_at_the_top(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $setPosters = fn(int $count) => $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/photo-competitions/seed-posters', ['count' => $count])
            ->assertOk();

        $this->assertSame(PostSeedPhotos::DEFAULT_POSTERS, PostSeedPhotos::posterCount());
        $two = PostSeedPhotos::posters()
            ->pluck('id')
            ->all();

        $setPosters(4);
        $this->assertSame(4, PostSeedPhotos::posterCount());
        $this->assertSame($two, PostSeedPhotos::posters()
            ->take(2)
            ->pluck('id')
            ->all());

        $setPosters(1);
        $this->assertSame([$two[0]], PostSeedPhotos::posters()
            ->pluck('id')
            ->all());

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/photo-competitions')
            ->assertJsonPath('data.seedPosters', 1);
        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/photo-competitions/seed-posters', ['count' => 101])
            ->assertJsonValidationErrors('count');
    }

    public function test_no_photos_are_posted_with_zero_posters(): void
    {
        $this->fakeUnsplash();
        Setting::query()
            ->updateOrCreate(['key' => PostSeedPhotos::SETTING_POSTERS], ['value' => 0]);
        $competition = $this->competition();
        $this->travelTo($competition->ends_at->copy()->subMinute());

        $this->artisan('app:post-seed-photos')->assertSuccessful();

        $this->assertSame(0, Photo::count());
    }

    public function test_every_competition_is_posted_by_the_same_seed_users(): void
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
        $this->assertSame(PostSeedPhotos::posters()->first()->id, $firstPoster);
    }

    public function test_skips_unsplash_photos_that_were_already_posted(): void
    {
        $this->fakeUnsplash(['already-used', 'brand-new']);
        $competition = $this->competition();
        Photo::factory()->create(['unsplash_id' => 'already-used']);
        $this->travelToDue($competition, 0);

        $this->artisan('app:post-seed-photos')->assertSuccessful();

        // Only one unused photo came back, so the post goes up with just that one.
        $photo = Photo::where('competition_id', $competition->id)->where('unsplash_id', 'brand-new')->sole();
        $this->assertSame(0, $photo->images()->count());
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
