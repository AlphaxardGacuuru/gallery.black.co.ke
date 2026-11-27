<?php

namespace Tests\Feature;

use App\Enums\UserEventName;
use App\Models\User;
use App\Models\UserEvent;
use App\Models\UserEventDailyTotal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserEventSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function event(UserEventName $event, ?User $user, string $at, array $attributes = []): void
    {
        UserEvent::query()->create([
            'user_id' => $user?->id,
            'anonymous_id' => $user ? null : (string) Str::uuid(),
            'event' => $event->value,
            'created_at' => $at,
            ...$attributes,
        ]);
    }

    private function total(string $event, string $source = '', string $route = ''): ?UserEventDailyTotal
    {
        return UserEventDailyTotal::query()
            ->where('event', $event)
            ->where('source', $source)
            ->where('route', $route)
            ->first();
    }

    public function test_yesterday_is_counted_by_event_source_and_page(): void
    {
        $this->travelTo('2026-11-27 09:00:00');
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->event(UserEventName::PHOTO_LIKED, $alice, '2026-11-26 08:00:00', ['source' => 'app']);
        $this->event(UserEventName::PHOTO_LIKED, $alice, '2026-11-26 09:00:00', ['source' => 'app']);
        $this->event(UserEventName::PHOTO_LIKED, $bob, '2026-11-26 10:00:00', ['source' => 'push_action']);
        $this->event(UserEventName::PAGE_VIEWED, null, '2026-11-26 11:00:00', ['properties' => ['route' => '/compete']]);
        $this->event(UserEventName::PHOTO_LIKED, $bob, '2026-11-27 08:00:00', ['source' => 'app']);

        $this->artisan('app:summarize-user-events', ['--days' => 1])->assertSuccessful();

        $appLikes = $this->total('photo.liked', 'app');
        $this->assertSame('2026-11-26', $appLikes->date->toDateString());
        $this->assertSame(2, $appLikes->events);
        $this->assertSame(1, $appLikes->users);
        $this->assertSame(1, $this->total('photo.liked', 'push_action')->events);

        $views = $this->total('page.viewed', route: '/compete');
        $this->assertSame(0, $views->users);
        $this->assertSame(1, $views->guests);

        $everyone = $this->total(UserEventDailyTotal::ALL_EVENTS);
        $this->assertSame(4, $everyone->events);
        $this->assertSame(2, $everyone->users);
        $this->assertSame(1, $everyone->guests);
    }

    public function test_recounting_a_day_replaces_its_totals(): void
    {
        $this->travelTo('2026-11-27 09:00:00');
        $user = User::factory()->create();
        $this->event(UserEventName::LOGGED_IN, $user, '2026-11-26 08:00:00');

        $this->artisan('app:summarize-user-events', ['--days' => 1]);
        $this->event(UserEventName::LOGGED_IN, $user, '2026-11-26 23:59:00');
        $this->artisan('app:summarize-user-events', ['--days' => 1]);

        $this->assertSame(1, UserEventDailyTotal::query()->where('event', 'user.logged_in')->count());
        $this->assertSame(2, $this->total('user.logged_in')->events);
    }

    public function test_a_quiet_day_has_no_totals(): void
    {
        $this->artisan('app:summarize-user-events', ['--days' => 1])->assertSuccessful();

        $this->assertSame(0, UserEventDailyTotal::query()->count());
    }

    public function test_only_old_page_views_are_pruned(): void
    {
        $user = User::factory()->create();
        $old = now()->subDays(UserEvent::PAGE_VIEW_RETENTION_DAYS + 1)->toDateTimeString();

        $this->event(UserEventName::PAGE_VIEWED, $user, $old);
        $this->event(UserEventName::PAGE_VIEWED, $user, now()->toDateTimeString());
        $this->event(UserEventName::PHOTO_PURCHASED, $user, $old);

        $this->artisan('model:prune', ['--model' => [UserEvent::class]]);

        $this->assertEqualsCanonicalizing(
            ['page.viewed', 'photo.purchased'],
            UserEvent::query()->pluck('event')->map->value->all()
        );
    }
}
