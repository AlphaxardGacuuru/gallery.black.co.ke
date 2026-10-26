<?php

namespace Tests\Feature\Admin;

use App\Models\KopokopoTransfer;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoPurchase;
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
        // A seed winner is marked paid without any money moving.
        PhotoCompetitionWinner::factory()->create([
            'user_id' => User::factory()->create(['is_seed' => true])->id,
            'prize_amount' => 700,
            'prize_paid_at' => now(),
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

        // Photo sales: paid sales count as revenue, pending ones don't, and
        // the photographer's share counts as a payout once confirmed.
        PhotoPurchase::factory()->paid()->create([
            'amount' => 200,
            'seller_amount' => 140,
            'payout_reference' => 'sale-ref',
            'payout_paid_at' => now(),
        ]);
        PhotoPurchase::factory()->create(['amount' => 300, 'seller_amount' => 210]);

        // Transfers: the prize's and referral's own transfers are already
        // counted above; a manual one counts once despite a repeated
        // webhook; a failed one never counts.
        $this->transfer('prize-ref', 500, 'Transferred');
        $this->transfer('referral-ref', 100, 'Transferred');
        $this->transfer('sale-ref', 140, 'Transferred');
        $this->transfer('manual-ref', 250, 'Transferred');
        $this->transfer('manual-ref', 250, 'Transferred');
        $this->transfer('failed-ref', 1000, 'Failed');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.finance.revenue', 290)
            ->assertJsonPath('data.finance.revenueBreakdown.extraEntries', 50)
            ->assertJsonPath('data.finance.revenueBreakdown.extraPhotos', 40)
            ->assertJsonPath('data.finance.revenueBreakdown.photoSales', 200)
            ->assertJsonPath('data.finance.payoutsBreakdown.prizes', 500)
            ->assertJsonPath('data.finance.payoutsBreakdown.referralRewards', 100)
            ->assertJsonPath('data.finance.payoutsBreakdown.photographerPayouts', 140)
            ->assertJsonPath('data.finance.payoutsBreakdown.otherTransfers', 250)
            ->assertJsonPath('data.finance.payouts', 990)
            ->assertJsonPath('data.finance.net', -700)
            ->assertJsonPath('data.referrals.totalReferrals', 2)
            ->assertJsonPath('data.referrals.totalReferrers', 2)
            ->assertJsonPath('data.referrals.totalPaidOut', 100)
            ->assertJsonPath('data.referrals.totalPendingPayout', 0);
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
