<?php

namespace App\Http\Controllers\Admin;

use App\Events\KopokopoTransferInitiated;
use App\Http\Controllers\Controller;
use App\Http\Services\KopokopoTransferService;
use App\Http\Services\Service;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReferralController extends Controller
{
    public function __construct(protected KopokopoTransferService $kopokopoTransferService)
    {
        //
    }

    /**
     * Totals, the configurable reward threshold/amount, and a leaderboard
     * of top referrers (each with what they're currently owed), for
     * deciding who to reward. The full history is paginated separately
     * via recent().
     */
    public function index(): JsonResponse
    {
        [$threshold, $rewardAmount] = $this->settings();

        $leaderboard = User::query()
            ->whereHas('referralsMade')
            ->withCount('referralsMade')
            ->orderByDesc('referrals_made_count')
            ->limit(20)
            ->get()
            ->map(function (User $user) use ($threshold, $rewardAmount) {
                $payout = Referral::eligiblePayout($user->id, $threshold, $rewardAmount);

                return [
                    'userId' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'referralsCount' => $user->referrals_made_count,
                    'eligibleAmount' => $payout['totalAmount'],
                ];
            });

        return response()->json([
            'data' => [
                'totalReferrals' => Referral::query()->count(),
                'totalReferrers' => $leaderboard->count() === 20
                    ? User::query()->whereHas('referralsMade')->count()
                    : $leaderboard->count(),
                'threshold' => $threshold,
                'rewardAmount' => $rewardAmount,
                'leaderboard' => $leaderboard,
            ],
        ]);
    }

    /**
     * Paginated referral history, most recent first.
     */
    public function recent(Request $request): JsonResponse
    {
        $referrals = Referral::query()
            ->with(['referrer', 'referred'])
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $referrals
                ->getCollection()
                ->map(fn(Referral $referral) => [
                    'id' => $referral->id,
                    'referrerName' => $referral->referrer?->name,
                    'referredName' => $referral->referred?->name,
                    'createdAt' => $referral->created_at,
                    'amountPaid' => $referral->amount_paid,
                    'paidAt' => $referral->paid_at,
                ]),
            'meta' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'total' => $referrals->total(),
            ],
        ]);
    }

    /**
     * Set how many referrals earn a reward, and how much (KES) that
     * reward is — split evenly across the referrals in each batch.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'threshold' => 'required|integer|min:1',
            'rewardAmount' => 'required|numeric|min:0',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'referral_threshold'],
            ['value' => $data['threshold']]
        );

        Setting::query()->updateOrCreate(
            ['key' => 'referral_reward_amount'],
            ['value' => $data['rewardAmount']]
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Pay a referrer for however many complete reward batches they've
     * accumulated, in one transfer, and mark those referrals paid.
     */
    public function pay(User $user): JsonResponse
    {
        [$threshold, $rewardAmount] = $this->settings();

        $payout = Referral::eligiblePayout($user->id, $threshold, $rewardAmount);

        [$status, $message, $data] = $this->kopokopoTransferService->payReferrer(
            $user,
            $payout['totalAmount'],
            $payout['referralIds'],
            $payout['perReferralAmount'],
        );

        if ($status === true) {
            KopokopoTransferInitiated::dispatch(
                Service::normalizePhoneNumber($user->phone),
                (float) $payout['totalAmount'],
                $user->name,
                'Black Gallery referral reward',
            );
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * @return array{0: int, 1: float}
     */
    private function settings(): array
    {
        $threshold = (int) (Setting::query()
            ->where('key', 'referral_threshold')
            ->value('value') ?? 5);

        $rewardAmount = (float) (Setting::query()
            ->where('key', 'referral_reward_amount')
            ->value('value') ?? 50);

        return [$threshold, $rewardAmount];
    }
}
