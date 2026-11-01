<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settings, users and integrations used to be open apiResources under
 * plain auth:sanctum, so any signed-in user could rewrite prices, take
 * over other accounts or read the payment webhooks. Admins manage them
 * through the dedicated /api/admin routes instead.
 */
class OpenResourceRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_cannot_change_app_settings(): void
    {
        Setting::updateOrCreate(['key' => 'photo_prize_tiers'], ['value' => '[1000]']);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/settings', ['key' => 'photo_prize_tiers', 'value' => '[999999]'])
            ->assertMethodNotAllowed();

        $this->assertSame('[1000]', Setting::firstWhere('key', 'photo_prize_tiers')->value);
    }

    public function test_users_cannot_edit_or_delete_other_accounts(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/users/{$victim->id}", ['email' => 'attacker@example.com', 'phone' => '0700000000'])
            ->assertMethodNotAllowed();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/users/{$victim->id}")
            ->assertMethodNotAllowed();

        $this->assertModelExists($victim);
        $this->assertNotSame('attacker@example.com', $victim->fresh()->email);
    }

    // GETs fall through to the SPA page, so check nothing leaks rather
    // than for a 404.
    public function test_users_cannot_list_accounts_or_integrations(): void
    {
        $other = User::factory()->create();
        $integration = Integration::create([
            'service' => 'kopokopo',
            'event_type' => 'buygoods_transaction_received',
            'external_id' => 'k2-subscription-1',
            'url' => 'https://example.com/secret-webhook',
            'status' => 'active',
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/users')
            ->assertDontSee($other->email);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/integrations')
            ->assertDontSee($integration->url);
    }
}
