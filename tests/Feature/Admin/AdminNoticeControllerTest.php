<?php

namespace Tests\Feature\Admin;

use App\Models\Notice;
use App\Models\NoticeEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNoticeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => config('admin.email')]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'key' => 'enter-challenge',
            'title' => 'Win KES {topPrize}',
            'body' => 'Post a photo this week.',
            'icon' => 'trophy',
            'primaryLabel' => 'Enter now',
            'primaryUrl' => '/photos/create',
            'secondaryLabel' => 'Later',
            'audience' => Notice::AUDIENCE_NOT_ENTERED,
            'priority' => 50,
            'cooldownHours' => 24,
            'maxImpressions' => 3,
            'stopAfterClick' => true,
            'isActive' => true,
            'startsAt' => null,
            'endsAt' => null,
            ...$overrides,
        ];
    }

    public function test_non_admins_cannot_manage_notices(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/notices')
            ->assertForbidden();
    }

    public function test_admin_can_create_a_notice(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/notices', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.key', 'enter-challenge')
            ->assertJsonPath('data.maxImpressions', 3);

        $this->assertDatabaseHas('notices', ['key' => 'enter-challenge', 'audience' => Notice::AUDIENCE_NOT_ENTERED]);
    }

    public function test_unknown_variables_are_rejected(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/notices', $this->payload(['title' => 'Win KES {topPrise}']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title' => '{topPrise} isn\'t a known variable.']);
    }

    public function test_index_reports_stats_per_notice(): void
    {
        $notice = Notice::factory()->create();
        $viewer = User::factory()->create();

        foreach ([NoticeEvent::ACTION_SHOWN, NoticeEvent::ACTION_SHOWN, NoticeEvent::ACTION_PRIMARY_CLICK] as $action) {
            NoticeEvent::query()->create([
                'notice_id' => $notice->id,
                'user_id' => $viewer->id,
                'version' => 'v1',
                'action' => $action,
            ]);
        }

        $response = $this->actingAs($this->admin(), 'sanctum')->getJson('/api/admin/notices')->assertOk();

        $stats = collect($response->json('data.notices'))->firstWhere('id', $notice->id)['stats'];

        $this->assertSame(2, $stats['shown']);
        $this->assertSame(1, $stats['uniqueViewers']);
        $this->assertSame(1, $stats['primaryClicks']);
        $this->assertContains('rewardAmount', array_column($response->json('data.variables'), 'name'));
    }

    public function test_event_log_can_be_filtered_by_action(): void
    {
        $notice = Notice::factory()->create();
        $viewer = User::factory()->create(['name' => 'Wanjiru']);

        foreach ([NoticeEvent::ACTION_SHOWN, NoticeEvent::ACTION_PRIMARY_CLICK] as $action) {
            NoticeEvent::query()->create([
                'notice_id' => $notice->id,
                'user_id' => $viewer->id,
                'version' => 'v1',
                'action' => $action,
            ]);
        }

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/notices/events?action=primary_click')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.userName', 'Wanjiru')
            ->assertJsonPath('data.0.noticeKey', $notice->key);
    }

    public function test_admin_can_update_global_limits(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/notices/limits', ['dailyLimit' => 3, 'minGapHours' => 2])
            ->assertOk();

        $this->assertSame(['dailyLimit' => 3, 'minGapHours' => 2], Notice::limits());
    }
}
