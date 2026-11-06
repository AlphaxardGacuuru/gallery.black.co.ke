<?php

namespace Tests\Feature\Admin;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => config('admin.email')]);
    }

    public function test_the_listing_reports_pwa_install_state_and_notification_settings(): void
    {
        $installed = User::factory()->create([
            'name' => 'Ivy Installed',
            'settings' => ['pwaInstalledAt' => '2026-01-01T00:00:00Z', 'competitionWonNotification' => false],
        ]);
        $installed->updatePushSubscription('https://push.example.com/a', 'key', 'token');

        $notInstalled = User::factory()->create(['name' => 'Nia Notyet']);

        $response = $this->actingAs($this->admin(), 'sanctum')->getJson('/api/admin/users?per_page=50');

        $response->assertOk();

        $byId = collect($response->json('data'))->keyBy('id');

        $this->assertNotEmpty($byId[$installed->id]['settings']['pwaInstalledAt']);
        $this->assertFalse($byId[$installed->id]['settings']['competitionWonNotification']);
        $this->assertSame(1, $byId[$installed->id]['pushSubscriptionsCount']);

        $this->assertEmpty($byId[$notInstalled->id]['settings'] ?? []);
        $this->assertSame(0, $byId[$notInstalled->id]['pushSubscriptionsCount']);
    }

    public function test_recording_a_pwa_install_is_reflected_in_the_admin_listing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/onboarding/pwa-installed')->assertOk();

        $response = $this->actingAs($this->admin(), 'sanctum')->getJson('/api/admin/users?per_page=50');

        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertNotEmpty($byId[$user->id]['settings']['pwaInstalledAt']);
    }

    public function test_admin_can_search_users_by_name_email_or_phone(): void
    {
        $admin = $this->admin();
        $byName = User::factory()->create(['name' => 'Wanjiku Kamau']);
        $byEmail = User::factory()->create(['email' => 'photog@example.org']);
        $byPhone = User::factory()->create(['phone' => '0711222333']);

        collect([['Wanjiku', $byName], ['photog@', $byEmail], ['11222', $byPhone]])
            ->each(fn (array $case) => $this->actingAs($admin, 'sanctum')
                ->getJson('/api/admin/users?per_page=50&search=' . urlencode($case[0]))
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id', $case[1]->id));
    }

    public function test_admin_can_filter_users(): void
    {
        $admin = $this->admin();
        $verified = User::factory()->create(['phone' => '0700000001']);
        $verified->forceFill(['verified' => true])->save();
        $noPhone = User::factory()->create(['phone' => null]);
        $seed = User::factory()->create(['phone' => '0700000002']);
        $seed->forceFill(['is_seed' => true])->save();
        $installed = User::factory()->create([
            'phone' => '0700000003',
            'settings' => ['pwaInstalledAt' => '2026-01-01T00:00:00Z'],
        ]);
        $referred = User::factory()->create(['phone' => '0700000004']);
        Referral::query()->create(['referrer_id' => $verified->id, 'referred_id' => $referred->id]);

        $ids = fn (string $query) => collect(
            $this->actingAs($admin, 'sanctum')
                ->getJson("/api/admin/users?per_page=50&{$query}")
                ->assertOk()
                ->json('data')
        )
            ->pluck('id')
            ->all();

        $this->assertEquals([$verified->id], $ids('verified=1'));
        $this->assertContains($noPhone->id, $ids('hasPhone=0'));
        $this->assertNotContains($verified->id, $ids('hasPhone=0'));
        $this->assertEquals([$seed->id], $ids('seed=1'));
        $this->assertNotContains($seed->id, $ids('seed=0'));
        $this->assertEquals([$installed->id], $ids('installed=1'));
        $this->assertEquals([$referred->id], $ids('referred=1'));
        $this->assertEquals([$referred->id], $ids('referred=1&hasPhone=1&seed=0'));
    }
}
