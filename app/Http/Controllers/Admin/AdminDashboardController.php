<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KopokopoTransfer;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoPurchase;
use App\Models\PhotoSlotPurchase;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totals = [
            'totalUsers' => (int) User::query()->count(),
            'totalCompetitions' => (int) PhotoCompetition::query()->count(),
            'totalPhotos' => (int) Photo::query()->count(),
            'totalLikes' => (int) Photo::query()->sum('likes_count'),
        ];

        $days = collect(range(13, 0))
            ->map(fn(int $offset) => now()->subDays($offset)->toDateString());

        $dailyRows = Photo::query()
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) as date, count(*) as aggregate')
            ->groupBy('date')
            ->pluck('aggregate', 'date');

        $dailyVolume = $days->map(fn(string $date) => [
            'date' => $date,
            'submitted' => (int) ($dailyRows[$date] ?? 0),
        ])->values();

        return response()->json([
            'data' => [
                'totals' => $totals,
                'dailyVolume' => $dailyVolume,
                'finance' => $this->finance(),
                'referrals' => Referral::summary(),
            ],
        ]);
    }

    /**
     * Money in (paid slot purchases) against money out (payouts Kopokopo
     * has confirmed). Prizes and referral rewards only count once their
     * transfer's webhook confirms it (prize_paid_at / paid_at), never at
     * initiation, so in-flight or failed payouts aren't counted as spent.
     * Seed winners are marked paid without any money moving, so they're
     * left out too.
     */
    private function finance(): array
    {
        $revenueByType = PhotoSlotPurchase::query()
            ->where('status', PhotoSlotPurchase::STATUS_PAID)
            ->selectRaw('type, sum(amount) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $extraEntries = (int) ($revenueByType[PhotoSlotPurchase::TYPE_EXTRA_ENTRY] ?? 0);
        $extraPhotos = (int) ($revenueByType[PhotoSlotPurchase::TYPE_EXTRA_PHOTO] ?? 0);
        $photoSales = (int) PhotoPurchase::query()->paid()->sum('amount');

        $prizes = (int) PhotoCompetitionWinner::query()
            ->whereNotNull('prize_paid_at')
            ->whereHas('user', fn($query) => $query->real())
            ->sum('prize_amount');

        $referralRewards = (int) round(
            (float) Referral::query()->whereNotNull('paid_at')->sum('amount_paid')
            + (float) Referral::query()->whereNotNull('photo_bonus_paid_at')->sum('photo_bonus_amount')
        );

        $photographerPayouts = (int) PhotoPurchase::query()
            ->whereNotNull('payout_paid_at')
            ->sum('seller_amount');

        $otherTransfers = $this->otherConfirmedTransfers();

        $revenue = $extraEntries + $extraPhotos + $photoSales;
        $payouts = $prizes + $referralRewards + $photographerPayouts + $otherTransfers;

        return [
            'revenue' => $revenue,
            'payouts' => $payouts,
            'net' => $revenue - $payouts,
            'revenueBreakdown' => [
                'extraEntries' => $extraEntries,
                'extraPhotos' => $extraPhotos,
                'photoSales' => $photoSales,
            ],
            'payoutsBreakdown' => [
                'prizes' => $prizes,
                'referralRewards' => $referralRewards,
                'photographerPayouts' => $photographerPayouts,
                'otherTransfers' => $otherTransfers,
            ],
        ];
    }

    /**
     * Confirmed transfers sent from the admin "send money" form, i.e. any
     * successful transfer that isn't a prize, referral reward or photographer
     * payout (those are already counted above, via the reference they
     * share). Kopokopo
     * statuses aren't a fixed enum, so success is matched in PHP with the
     * model's own heuristic rather than in SQL, and a webhook delivered
     * twice for the same transfer is only counted once.
     */
    private function otherConfirmedTransfers(): int
    {
        $linkedReferences = PhotoCompetitionWinner::query()
            ->whereNotNull('kopokopo_reference')
            ->pluck('kopokopo_reference')
            ->merge(Referral::query()->whereNotNull('kopokopo_reference')->pluck('kopokopo_reference'))
            ->merge(Referral::query()->whereNotNull('photo_bonus_reference')->pluck('photo_bonus_reference'))
            ->merge(PhotoPurchase::query()->whereNotNull('payout_reference')->pluck('payout_reference'))
            ->flip();

        return (int) round(KopokopoTransfer::query()
            ->whereNotNull('kopokopo_id')
            ->get(['kopokopo_id', 'amount', 'status'])
            ->filter(fn(KopokopoTransfer $transfer) => $transfer->isConfirmedSuccess())
            ->reject(fn(KopokopoTransfer $transfer) => $linkedReferences->has($transfer->kopokopo_id))
            ->unique('kopokopo_id')
            ->sum(fn(KopokopoTransfer $transfer) => (float) $transfer->amount));
    }
}
