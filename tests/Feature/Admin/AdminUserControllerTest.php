<?php

namespace Tests\Feature\Admin;

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
}
