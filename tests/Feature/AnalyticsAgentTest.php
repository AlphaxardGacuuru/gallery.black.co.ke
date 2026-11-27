<?php

namespace Tests\Feature;

use App\Ai\Agents\AnalyticsAgent;
use App\Ai\Tools\Analytics\EventTotalsTool;
use App\Ai\Tools\Analytics\FunnelTool;
use App\Enums\UserEventName;
use App\Models\AiUsage;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserEvent;
use App\Services\UsageAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AnalyticsAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-27 12:00:00');
    }

    private function event(UserEventName $event, User $user, string $at, array $attributes = []): void
    {
        UserEvent::query()->create([
            'user_id' => $user->id,
            'event' => $event->value,
            'created_at' => $at,
            ...$attributes,
        ]);
    }

    private function analytics(): UsageAnalyticsService
    {
        return app(UsageAnalyticsService::class);
    }

    public function test_event_totals_combine_finished_days_with_today(): void
    {
        $user = User::factory()->create();
        $this->event(UserEventName::PHOTO_LIKED, $user, '2026-11-26 10:00:00', ['source' => 'app']);
        $this->artisan('app:summarize-user-events', ['--days' => 1]);
        $this->event(UserEventName::PHOTO_LIKED, $user, '2026-11-27 09:00:00', ['source' => 'push_action']);

        $result = $this->analytics()->eventTotals(
            'photo.liked',
            CarbonImmutable::parse('2026-11-26'),
            CarbonImmutable::parse('2026-11-27'),
            'source',
        );

        $this->assertSame(
            [['source' => 'app', 'events' => 1, 'users' => 1, 'guests' => 0], ['source' => 'push_action', 'events' => 1, 'users' => 1, 'guests' => 0]],
            $result['rows']
        );
    }

    public function test_active_users_counts_each_person_once(): void
    {
        $user = User::factory()->create();
        $this->event(UserEventName::PHOTO_LIKED, $user, '2026-11-25 10:00:00');
        $this->event(UserEventName::LOGGED_IN, $user, '2026-11-26 10:00:00');
        $this->event(UserEventName::LOGGED_IN, User::factory()->create(), '2026-11-26 11:00:00');

        $result = $this->analytics()->activeUsers(CarbonImmutable::parse('2026-11-20'), CarbonImmutable::parse('2026-11-27'));

        $this->assertSame(2, $result['users']);
    }

    public function test_a_funnel_only_counts_steps_taken_in_order(): void
    {
        $inOrder = User::factory()->create();
        $outOfOrder = User::factory()->create();
        $stopped = User::factory()->create();

        $this->event(UserEventName::SIGNED_UP, $inOrder, '2026-11-20 10:00:00');
        $this->event(UserEventName::PHOTO_SUBMITTED, $inOrder, '2026-11-21 10:00:00');
        $this->event(UserEventName::PHOTO_SUBMITTED, $outOfOrder, '2026-11-20 09:00:00');
        $this->event(UserEventName::SIGNED_UP, $outOfOrder, '2026-11-20 10:00:00');
        $this->event(UserEventName::SIGNED_UP, $stopped, '2026-11-20 10:00:00');

        $result = $this->analytics()->funnel(
            ['user.signed_up', 'photo.submitted'],
            CarbonImmutable::parse('2026-11-20'),
            CarbonImmutable::parse('2026-11-27'),
        );

        $this->assertSame(3, $result['steps'][0]['users']);
        $this->assertSame(1, $result['steps'][1]['users']);
        $this->assertSame(33.3, $result['steps'][1]['percent_of_first_step']);
    }

    public function test_retention_follows_each_sign_up_week(): void
    {
        $returning = User::factory()->create();
        $gone = User::factory()->create();

        $this->event(UserEventName::SIGNED_UP, $returning, '2026-11-09 10:00:00');
        $this->event(UserEventName::SIGNED_UP, $gone, '2026-11-10 10:00:00');
        $this->event(UserEventName::LOGGED_IN, $returning, '2026-11-17 10:00:00');

        $result = $this->analytics()->retention('user.signed_up', 2, CarbonImmutable::parse('2026-11-09'));

        $cohort = $result['cohorts'][0];
        $this->assertSame('2026-11-09', $cohort['week_of']);
        $this->assertSame(2, $cohort['users']);
        $this->assertSame(100.0, $cohort['retained'][0]['percent']);
        $this->assertSame(['week' => 1, 'users' => 1, 'percent' => 50.0], $cohort['retained'][1]);
    }

    public function test_business_stats_leave_out_seed_accounts(): void
    {
        User::factory()->create(['created_at' => now()]);
        User::factory()->create(['is_seed' => true, 'created_at' => now()]);

        $result = $this->analytics()->businessStats(CarbonImmutable::parse('2026-11-27'), CarbonImmutable::parse('2026-11-27'));

        $this->assertSame(1, $result['sign_ups']);
    }

    public function test_tools_return_json_and_ignore_unknown_funnel_steps(): void
    {
        $this->event(UserEventName::SIGNED_UP, User::factory()->create(), '2026-11-27 10:00:00');

        $totals = json_decode(app(EventTotalsTool::class)->handle(new Request([
            'event' => 'user.signed_up',
            'group_by' => 'none',
        ])), true);
        $funnel = json_decode(app(FunnelTool::class)->handle(new Request([
            'steps' => ['user.signed_up', 'drop table users'],
        ])), true);

        $this->assertSame(1, $totals['rows'][0]['events']);
        $this->assertSame(['user.signed_up'], array_column($funnel['steps'], 'step'));
    }

    public function test_admins_get_an_answer_and_the_tools_it_used(): void
    {
        AnalyticsAgent::fake(['12 people signed up this week.']);
        $admin = User::factory()->create(['email' => config('admin.email')]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/analytics/ask', [
                'messages' => [['role' => 'user', 'content' => 'How many sign-ups this week?']],
            ])
            ->assertOk()
            ->assertJsonPath('data.answer', '12 people signed up this week.')
            ->assertJsonPath('data.toolCalls', []);

        $this->assertSame(1, AiUsage::query()->where('feature', AiUsage::FEATURE_ANALYTICS)->count());
    }

    public function test_only_admins_can_ask(): void
    {
        AnalyticsAgent::fake(['Nope.']);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/admin/analytics/ask', [
                'messages' => [['role' => 'user', 'content' => 'How many sign-ups?']],
            ])
            ->assertForbidden();
    }

    public function test_nothing_is_asked_while_agents_are_switched_off(): void
    {
        AnalyticsAgent::fake(['Should not be used.']);
        Setting::query()->updateOrCreate(['key' => AiUsage::SETTING_AGENTS_ENABLED], ['value' => false]);

        $this->actingAs(User::factory()->create(['email' => config('admin.email')]), 'sanctum')
            ->postJson('/api/admin/analytics/ask', [
                'messages' => [['role' => 'user', 'content' => 'How many sign-ups?']],
            ])
            ->assertJsonValidationErrors('messages');

        AnalyticsAgent::assertNeverPrompted();
    }
}
