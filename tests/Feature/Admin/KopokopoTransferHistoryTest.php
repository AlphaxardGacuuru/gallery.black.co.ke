<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KopokopoTransferHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_the_recipient_from_the_webhook_destination(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);

        $this->postJson('/api/kopokopo-transfers', $this->webhook([
            'destinations' => [[
                'type' => 'mobile_wallet',
                'amount' => 500,
                'nickname' => 'Jane Wanjiku',
                'phone_number' => '254700123456',
            ]],
        ]))->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/kopokopo-transfers')
            ->assertOk()
            ->assertJsonPath('data.0.recipientName', 'Jane Wanjiku')
            ->assertJsonPath('data.0.recipientPhone', '254700123456')
            // No metadata userId, so there's no "recorded by" user, which
            // used to crash the whole listing.
            ->assertJsonPath('data.0.user', null);
    }

    public function test_history_falls_back_to_the_disbursement_when_destinations_are_missing(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);

        $this->postJson('/api/kopokopo-transfers', $this->webhook([
            'transfer_batches' => [[
                'status' => 'Transferred',
                'disbursements' => [[
                    'amount' => '500.0',
                    'nickname' => 'Jane Wanjiku',
                    'phone_number' => '254700123456',
                ]],
            ]],
        ]))->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/kopokopo-transfers')
            ->assertOk()
            ->assertJsonPath('data.0.recipientName', 'Jane Wanjiku')
            ->assertJsonPath('data.0.recipientPhone', '254700123456');
    }

    private function webhook(array $attributes): array
    {
        return [
            'data' => [
                'id' => 'transfer-123',
                'attributes' => array_merge([
                    'status' => 'Processed',
                    'created_at' => now()->toIso8601String(),
                    'currency' => 'KES',
                    'metadata' => [],
                ], $attributes),
            ],
        ];
    }
}
