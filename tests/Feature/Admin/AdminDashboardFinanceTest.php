<?php

namespace Tests\Feature\Admin;

use App\Models\KopokopoTransfer;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoSlotPurchase;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summarises_revenue_and_confirmed_payouts(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);

        // Revenue: only paid purchases count.
        PhotoSlotPurchase::factory()->paid()->create(['type' => PhotoSlotPurchase::TYPE_EXTRA_ENTRY, 'amount' => 50]);
        PhotoSlotPurchase::factory()->paid()->create(['type' => PhotoSlotPurchase::TYPE_EXTRA_PHOTO, 'amount' => 20]);
        PhotoSlotPurchase::factory()->paid()->create(['type' => PhotoSlotPurchase::TYPE_EXTRA_PHOTO, 'amount' => 20]);
        PhotoSlotPurchase::factory()->create(['amount' => 50]);

        // Prizes: confirmed counts, in-flight doesn't.
        PhotoCompetitionWinner::factory()->create([
            'prize_amount' => 500,
            'prize_paid_at' => now(),
            'kopokopo_reference' => 'prize-ref',
        ]);
        PhotoCompetitionWinner::factory()->create([
            'prize_amount' => 300,
            'kopokopo_reference' => 'in-flight-ref',
        ]);

        // Referral rewards: confirmed counts, unpaid doesn't.
        Referral::create([
            'referrer_id' => User::factory()->create()->id,
            'referred_id' => User::factory()->create()->id,
            'paid_at' => now(),
            'amount_paid' => 100,
            'kopokopo_reference' => 'referral-ref',
        ]);
        Referral::create([
            'referrer_id' => User::factory()->create()->id,
            'referred_id' => User::factory()->create()->id,
        ]);

        // Transfers: the prize's and referral's own transfers are already
        // counted above; a manual one counts once despite a repeated
        // webhook; a failed one never counts.
        $this->transfer('prize-ref', 500, 'Transferred');
        $this->transfer('referral-ref', 100, 'Transferred');
        $this->transfer('manual-ref', 250, 'Transferred');
        $this->transfer('manual-ref', 250, 'Transferred');
        $this->transfer('failed-ref', 1000, 'Failed');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.finance.revenue', 90)
            ->assertJsonPath('data.finance.revenueBreakdown.extraEntries', 50)
            ->assertJsonPath('data.finance.revenueBreakdown.extraPhotos', 40)
            ->assertJsonPath('data.finance.payoutsBreakdown.prizes', 500)
            ->assertJsonPath('data.finance.payoutsBreakdown.referralRewards', 100)
            ->assertJsonPath('data.finance.payoutsBreakdown.otherTransfers', 250)
            ->assertJsonPath('data.finance.payouts', 850)
            ->assertJsonPath('data.finance.net', -760);
    }

    private function transfer(string $reference, int $amount, string $status): void
    {
        $transfer = new KopokopoTransfer;
        $transfer->kopokopo_id = $reference;
        $transfer->amount = (string) $amount;
        $transfer->currency = 'KES';
        $transfer->status = $status;
        $transfer->save();
    }
}
