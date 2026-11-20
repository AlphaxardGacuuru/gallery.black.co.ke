<?php

namespace Tests\Feature\Admin;

use App\Models\KopokopoTransfer;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoPurchase;
use App\Models\Referral;
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

    public function test_history_can_be_narrowed_to_one_kind_of_payout(): void
    {
        $admin = User::factory()
            ->create(['email' => config('admin.email')]);

        PhotoCompetitionWinner::factory()
            ->create(['kopokopo_reference' => 'prize-ref']);
        Referral::query()
            ->create([
                'referrer_id' => User::factory()->create()->id,
                'referred_id' => User::factory()->create()->id,
                'kopokopo_reference' => 'referral-ref',
                'photo_bonus_reference' => 'bonus-ref',
            ]);
        PhotoPurchase::factory()
            ->create(['payout_reference' => 'sale-ref']);

        collect(['prize-ref', 'referral-ref', 'bonus-ref', 'sale-ref', 'manual-ref', null])
            ->each(fn (?string $reference) => KopokopoTransfer::query()
                ->create(['kopokopo_id' => $reference, 'amount' => '100', 'currency' => 'KES']));

        $kopokopoIds = fn (string $type) => $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/kopokopo-transfers?type={$type}")
            ->assertOk()
            ->collect('data')
            ->pluck('kopokopoId')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['prize-ref'], $kopokopoIds(KopokopoTransfer::TYPE_WINNINGS));
        $this->assertSame(['bonus-ref', 'referral-ref'], $kopokopoIds(KopokopoTransfer::TYPE_REFERRALS));
        $this->assertSame(['sale-ref'], $kopokopoIds(KopokopoTransfer::TYPE_PHOTOGRAPHERS));
        $this->assertSame([null, 'manual-ref'], $kopokopoIds(KopokopoTransfer::TYPE_OTHER));

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/kopokopo-transfers')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/kopokopo-transfers?type=unknown')
            ->assertUnprocessable();
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
