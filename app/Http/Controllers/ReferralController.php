<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * The current referral reward threshold/amount, so any signed-in user
     * (not just admins) can see what referring friends earns them.
     */
    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => [
                'threshold' => (int) (Setting::query()
                    ->where('key', 'referral_threshold')
                    ->value('value') ?? 5),
                'rewardAmount' => (float) (Setting::query()
                    ->where('key', 'referral_reward_amount')
                    ->value('value') ?? 50),
            ],
        ]);
    }

    /**
     * The authenticated user's own referral history, most recent first.
     */
    public function mine(Request $request): JsonResponse
    {
        $referrals = Referral::query()
            ->where('referrer_id', $request->user()->id)
            ->with('referred')
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $referrals->getCollection()->map(fn(Referral $referral) => [
                'id' => $referral->id,
                'referredName' => $referral->referred?->name,
                'createdAt' => $referral->created_at,
            ]),
            'meta' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'total' => $referrals->total(),
            ],
        ]);
    }
}
