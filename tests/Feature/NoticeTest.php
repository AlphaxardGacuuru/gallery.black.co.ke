<?php

namespace Tests\Feature;

use App\Http\Services\NoticeService;
use App\Models\Notice;
use App\Models\NoticeEvent;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticeTest extends TestCase
{
    use RefreshDatabase;

    private function next(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user, 'sanctum')->getJson('/api/notices/next')->assertOk();
    }

    private function record(User $user, array $notice, string $action): void
    {
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/notices/{$notice['id']}/events", [
                'version' => $notice['version'],
                'action' => $action,
            ])
            ->assertCreated();
    }

    public function test_the_seeded_referral_notice_is_served_with_current_settings_filled_in(): void
    {
        Setting::query()->create(['key' => 'referral_reward_amount', 'value' => 500]);
        Setting::query()->create(['key' => 'referral_threshold', 'value' => 3]);

        $this->next(User::factory()->create())
            ->assertJsonPath('data.title', 'Earn KES 500')
            ->assertJsonPath('data.body', 'Refer 3 friends and get KES 500 sent straight to your M-Pesa.')
            ->assertJsonPath('data.primaryUrl', '/settings/referrals');
    }

    public function test_the_global_gap_blocks_a_second_notice_right_after_one_is_shown(): void
    {
        $user = User::factory()->create();
        Notice::factory()->create(['priority' => 1]);

        $first = $this->next($user)->json('data');
        $this->record($user, $first, NoticeEvent::ACTION_SHOWN);

        $this->next($user)->assertJsonPath('data', null);

        $this->travel(Notice::DEFAULT_MIN_GAP_HOURS + 1)->hours();

        // The referral notice is on cooldown, so the next one in line shows.
        $this->next($user)->assertJsonPath('data.id', fn($id) => $id !== $first['id']);
    }

    public function test_the_daily_limit_caps_notices_per_rolling_24_hours(): void
    {
        Setting::query()->create(['key' => 'notices_daily_limit', 'value' => 1]);
        Setting::query()->create(['key' => 'notices_min_gap_hours', 'value' => 0]);
        Notice::factory()->create();

        $user = User::factory()->create();
        $this->record($user, $this->next($user)->json('data'), NoticeEvent::ACTION_SHOWN);

        $this->next($user)->assertJsonPath('data', null);
    }

    public function test_a_notice_waits_out_its_cooldown(): void
    {
        Setting::query()->create(['key' => 'notices_min_gap_hours', 'value' => 0]);
        Notice::query()->delete();
        $notice = Notice::factory()->create(['cooldown_hours' => 12]);

        $user = User::factory()->create();
        $this->record($user, $this->next($user)->json('data'), NoticeEvent::ACTION_SHOWN);

        $this->travel(11)->hours();
        $this->next($user)->assertJsonPath('data', null);

        $this->travel(2)->hours();
        $this->next($user)->assertJsonPath('data.id', $notice->id);
    }

    public function test_stop_after_click_hides_a_notice_once_its_main_button_is_clicked(): void
    {
        Setting::query()->create(['key' => 'notices_min_gap_hours', 'value' => 0]);
        Notice::query()->delete();
        Notice::factory()->create(['cooldown_hours' => 0, 'stop_after_click' => true]);

        $user = User::factory()->create();
        $served = $this->next($user)->json('data');
        $this->record($user, $served, NoticeEvent::ACTION_SHOWN);
        $this->record($user, $served, NoticeEvent::ACTION_PRIMARY_CLICK);

        $this->next($user)->assertJsonPath('data', null);
    }

    public function test_a_prize_change_makes_a_new_version_that_skips_the_old_cooldown(): void
    {
        Setting::query()->create(['key' => 'notices_min_gap_hours', 'value' => 0]);
        $user = User::factory()->create();

        $old = $this->next($user)->json('data');
        $this->record($user, $old, NoticeEvent::ACTION_SHOWN);
        $this->next($user)->assertJsonPath('data', null);

        Setting::query()->create(['key' => 'referral_reward_amount', 'value' => 1000]);

        $this->next($user)
            ->assertJsonPath('data.title', 'Earn KES 1,000')
            ->assertJsonPath('data.version', fn($version) => $version !== $old['version']);
    }

    public function test_repeated_dismissals_stretch_the_cooldown(): void
    {
        Setting::query()->create(['key' => 'notices_min_gap_hours', 'value' => 0]);
        Setting::query()->create(['key' => 'notices_daily_limit', 'value' => 24]);
        Notice::query()->delete();
        Notice::factory()->create(['cooldown_hours' => 10]);
        $user = User::factory()->create();

        foreach (range(1, NoticeService::FATIGUE_AFTER) as $ignored) {
            $served = $this->next($user)->json('data');
            $this->assertNotNull($served);
            $this->record($user, $served, NoticeEvent::ACTION_SHOWN);
            $this->record($user, $served, NoticeEvent::ACTION_DISMISSED);
            $this->travel(10)->hours();
        }

        // Cooldown has doubled to 20 hours, and 10 have passed.
        $this->next($user)->assertJsonPath('data', null);
        $this->travel(10)->hours();
        $this->assertNotNull($this->next($user)->json('data'));
    }

    public function test_never_referred_audience_skips_users_who_have_referred_someone(): void
    {
        Notice::query()->delete();
        Notice::factory()->create(['audience' => Notice::AUDIENCE_NEVER_REFERRED]);

        $referrer = User::factory()->create();
        Referral::query()->create([
            'referrer_id' => $referrer->id,
            'referred_id' => User::factory()->create()->id,
        ]);

        $this->next($referrer)->assertJsonPath('data', null);
        $this->assertNotNull($this->next(User::factory()->create())->json('data'));
    }

    public function test_a_notice_with_an_unfillable_variable_is_skipped(): void
    {
        Notice::query()->delete();
        Notice::factory()->create(['title' => 'Hi {firstName}']);

        $this->next(User::factory()->create(['name' => '']))->assertJsonPath('data', null);
    }

    public function test_events_reject_unknown_actions(): void
    {
        $notice = Notice::query()->firstOrFail();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/notices/{$notice->id}/events", ['version' => 'abc', 'action' => 'hacked'])
            ->assertUnprocessable();
    }
}
