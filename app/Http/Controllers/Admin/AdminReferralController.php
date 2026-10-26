<?php

namespace App\Http\Controllers\Admin;

use App\Events\ReferralAttachedEvent;
use App\Http\Controllers\Controller;
use App\Http\Services\KopokopoTransferService;
use App\Http\Services\ReferralService;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminReferralController extends Controller
{
    public function __construct(
        protected KopokopoTransferService $kopokopoTransferService,
        protected ReferralService $referralService,
    ) {
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
        ['threshold' => $threshold, 'rewardAmount' => $rewardAmount, 'photoBonusAmount' => $photoBonusAmount]
            = $this->referralService->settings();

        $leaderboard = User::query()
            ->whereHas('referralsMade')
            ->withCount('referralsMade')
            ->orderByDesc('referrals_made_count')
            ->limit(20)
            ->get()
            ->map(function (User $user) use ($threshold, $rewardAmount, $photoBonusAmount) {
                $payout = Referral::eligiblePayout($user->id, $threshold, $rewardAmount, $photoBonusAmount);

                return [
                    'userId' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'verified' => (bool) $user->verified,
                    'phone' => $user->phone,
                    'referralsCount' => $user->referrals_made_count,
                    'eligibleAmount' => $payout['totalAmount'],
                ];
            });

        return response()->json([
            'data' => [
                ...Referral::summary(),
                'threshold' => $threshold,
                'rewardAmount' => $rewardAmount,
                'photoBonusAmount' => $photoBonusAmount,
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
            ->withReferredHasPosted()
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
                    'referredHasPosted' => (bool) $referral->referred_has_posted,
                    'photoBonusAmount' => $referral->photo_bonus_amount,
                    'photoBonusPaidAt' => $referral->photo_bonus_paid_at,
                ]),
            'meta' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'total' => $referrals->total(),
            ],
        ]);
    }

    /**
     * Credit $user to a referrer by hand, for someone who signed up without
     * using a referral link. Same rules as Referral::record(): no
     * self-referral, and a user can only ever be referred once. The
     * referrer is notified via ReferralAttachedEvent, the same notification as a
     * signup through their link.
     */
    public function attach(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'referrerId' => ['required', 'string', 'exists:users,id'],
        ]);

        if ($data['referrerId'] === $user->id) {
            throw ValidationException::withMessages([
                'referrerId' => 'A user can\'t refer themselves.',
            ]);
        }

        if ($user->referral()->exists()) {
            throw ValidationException::withMessages([
                'referrerId' => "{$user->name} is already credited to a referrer.",
            ]);
        }

        $referral = Referral::query()->create([
            'referrer_id' => $data['referrerId'],
            'referred_id' => $user->id,
        ]);

        ReferralAttachedEvent::dispatch($referral);

        return response()->json([
            'data' => [
                'id' => $referral->id,
                'referrerName' => $referral->referrer?->name,
                'referredName' => $user->name,
            ],
        ], 201);
    }

    /**
     * Set how many referrals earn a reward, and how much (KES) that
     * reward is (split evenly across the referrals in each batch), plus the
     * one-off bonus for each referred user who posts a photo.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'threshold' => 'required|integer|min:1',
            'rewardAmount' => 'required|numeric|min:0',
            'photoBonusAmount' => 'required|numeric|min:0',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'referral_threshold'],
            ['value' => $data['threshold']]
        );

        Setting::query()->updateOrCreate(
            ['key' => 'referral_reward_amount'],
            ['value' => $data['rewardAmount']]
        );

        Setting::query()->updateOrCreate(
            ['key' => 'referral_photo_bonus_amount'],
            ['value' => $data['photoBonusAmount']]
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Pay a referrer for however many complete reward batches and photo
     * bonuses they've accumulated, in one transfer.
     */
    public function pay(User $user): JsonResponse
    {
        ['threshold' => $threshold, 'rewardAmount' => $rewardAmount, 'photoBonusAmount' => $photoBonusAmount]
            = $this->referralService->settings();

        $payout = Referral::eligiblePayout($user->id, $threshold, $rewardAmount, $photoBonusAmount);

        [$status, $message, $data] = $this->kopokopoTransferService->payReferrer(
            $user,
            $payout['totalAmount'],
            $payout['referralIds'],
            $payout['perReferralAmount'],
            $payout['photoBonusReferralIds'],
            $payout['photoBonusAmount'],
        );

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
