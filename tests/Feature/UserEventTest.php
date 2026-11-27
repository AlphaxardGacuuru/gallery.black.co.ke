<?php

namespace Tests\Feature;

use App\Enums\UserEventName;
use App\Enums\VerificationReason;
use App\Http\Services\UserService;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserEvent;
use App\Services\EventRecorder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function activePhoto(): Photo
    {
        $this->artisan('app:start-photo-competition');

        return PhotoCompetition::first()
            ->photos()
            ->create([
                'user_id' => User::factory()->create()->id,
                'disk' => 'public',
                'path' => 'photos/test.jpg',
            ]);
    }

    private function events(UserEventName $event): Collection
    {
        return UserEvent::query()
            ->where('event', $event->value)
            ->get();
    }

    public function test_seed_accounts_and_admins_are_not_recorded(): void
    {
        $recorder = app(EventRecorder::class);

        $recorder->record(UserEventName::LOGGED_IN, User::factory()->create(['is_seed' => true]));
        $recorder->record(UserEventName::LOGGED_IN, User::factory()->create(['email' => config('admin.email')]));
        $recorder->record(UserEventName::LOGGED_IN, $user = User::factory()->create());

        $this->assertSame([$user->id], $this->events(UserEventName::LOGGED_IN)->pluck('user_id')->all());
    }

    public function test_logging_in_is_recorded_with_its_method(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertSame('password', $this->events(UserEventName::LOGGED_IN)->sole()->source);
    }

    public function test_liking_in_the_app_is_recorded_with_the_photo_and_competition(): void
    {
        $photo = $this->activePhoto();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->postJson("/api/photos/{$photo->id}/like")
            ->assertOk();

        $event = $this->events(UserEventName::PHOTO_LIKED)->sole();
        $this->assertSame($viewer->id, $event->user_id);
        $this->assertSame($photo->id, $event->photo_id);
        $this->assertSame($photo->competition_id, $event->competition_id);
        $this->assertSame('app', $event->source);
    }

    public function test_an_admin_adding_a_like_is_not_recorded_as_the_users_own(): void
    {
        $photo = $this->activePhoto();
        $user = User::factory()->create();

        $this->actingAs(User::factory()->create(['email' => config('admin.email')]), 'sanctum')
            ->postJson("/api/admin/photos/{$photo->id}/likes/{$user->id}")
            ->assertOk();

        $this->assertCount(0, $this->events(UserEventName::PHOTO_LIKED));
    }

    public function test_bookmarking_is_recorded_once_and_unbookmarking_too(): void
    {
        $photo = $this->activePhoto();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/photos/{$photo->id}/bookmark");
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/photos/{$photo->id}/bookmark");
        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/photos/{$photo->id}/bookmark");
        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/photos/{$photo->id}/bookmark");

        $this->assertCount(1, $this->events(UserEventName::PHOTO_BOOKMARKED));
        $this->assertCount(1, $this->events(UserEventName::PHOTO_UNBOOKMARKED));
    }

    public function test_only_a_first_phone_number_is_recorded(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $service = app(UserService::class);

        $service->updateProfile($user, ['phone' => '0712345678']);
        $service->updateProfile($user->fresh(), ['phone' => '0712345679']);

        $this->assertCount(1, $this->events(UserEventName::PHONE_ADDED));
    }

    public function test_a_referral_link_sign_up_is_recorded_once(): void
    {
        $referrer = User::factory()->create();
        $referred = User::factory()->create();

        Referral::record($referrer->id, $referred);
        Referral::record($referrer->id, $referred);

        $event = $this->events(UserEventName::REFERRED)->sole();
        $this->assertSame('link', $event->source);
        $this->assertSame($referrer->id, $event->properties['referrer_id']);
    }

    public function test_winning_records_the_prize_and_the_badge(): void
    {
        Setting::query()->updateOrCreate(['key' => 'photo_prize_tiers'], ['value' => [500]]);
        $photo = $this->activePhoto();
        $photo->update(['likes_count' => 5]);

        $this->artisan('app:end-photo-competition')->assertSuccessful();

        $prize = $this->events(UserEventName::PRIZE_WON)->sole();
        $this->assertSame($photo->user_id, $prize->user_id);
        $this->assertEquals(['position' => 1, 'amount' => 500], $prize->properties);
        $this->assertSame(
            VerificationReason::COMPETITION_WINNER->value,
            $this->events(UserEventName::VERIFIED)->sole()->properties['reason']
        );
    }

    public function test_deleting_an_account_keeps_its_events_without_the_person(): void
    {
        $user = User::factory()->create();
        app(EventRecorder::class)->record(UserEventName::LOGGED_IN, $user);

        $user->delete();

        $this->assertNull($this->events(UserEventName::LOGGED_IN)->sole()->user_id);
    }

    public function test_a_guest_page_view_is_recorded_against_their_browser(): void
    {
        $anonymousId = (string) Str::uuid();

        $this->postJson('/api/events', [
            'event' => 'page.viewed',
            'anonymousId' => $anonymousId,
            'path' => '/compete',
            'properties' => ['route' => '/compete'],
        ])->assertNoContent();

        $event = $this->events(UserEventName::PAGE_VIEWED)->sole();
        $this->assertNull($event->user_id);
        $this->assertSame($anonymousId, $event->anonymous_id);
        $this->assertSame('/compete', $event->path);
        $this->assertSame(['route' => '/compete'], $event->properties);
    }

    public function test_signing_in_links_the_guests_earlier_events_to_the_account(): void
    {
        $anonymousId = (string) Str::uuid();
        $user = User::factory()->create();

        $this->postJson('/api/events', ['event' => 'page.viewed', 'anonymousId' => $anonymousId]);
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', ['event' => 'page.viewed', 'anonymousId' => $anonymousId])
            ->assertNoContent();

        $this->assertSame(
            [$user->id, $user->id],
            $this->events(UserEventName::PAGE_VIEWED)->pluck('user_id')->all()
        );
    }

    public function test_a_share_from_the_browser_carries_the_photo_and_competition(): void
    {
        $photo = $this->activePhoto();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/events', [
                'event' => 'photo.shared',
                'anonymousId' => (string) Str::uuid(),
                'photoId' => $photo->id,
                'source' => 'copied_link',
            ])
            ->assertNoContent();

        $event = $this->events(UserEventName::PHOTO_SHARED)->sole();
        $this->assertSame($photo->id, $event->photo_id);
        $this->assertSame($photo->competition_id, $event->competition_id);
        $this->assertSame('copied_link', $event->source);
    }

    public function test_the_browser_cannot_send_server_side_events(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/events', [
                'event' => UserEventName::PHOTO_PURCHASED->value,
                'anonymousId' => (string) Str::uuid(),
            ])
            ->assertJsonValidationErrors('event');

        $this->assertSame(0, UserEvent::query()->count());
    }

    public function test_installing_the_app_is_recorded_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/onboarding/pwa-installed');
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/onboarding/pwa-installed');

        $this->assertCount(1, $this->events(UserEventName::APP_INSTALLED));
    }
}
