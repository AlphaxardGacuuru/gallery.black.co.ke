<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReferralController extends Controller
{
    /**
     * Totals and a leaderboard of top referrers, for deciding who to
     * reward. The full history is paginated separately via recent().
     */
    public function index(): JsonResponse
    {
        $leaderboard = User::query()
            ->whereHas('referralsMade')
            ->withCount('referralsMade')
            ->orderByDesc('referrals_made_count')
            ->limit(20)
            ->get()
            ->map(fn(User $user) => [
                'userId' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'referralsCount' => $user->referrals_made_count,
            ]);

        return response()->json([
            'data' => [
                'totalReferrals' => Referral::query()->count(),
                'totalReferrers' => $leaderboard->count() === 20
                    ? User::query()->whereHas('referralsMade')->count()
                    : $leaderboard->count(),
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
            'data' => $referrals->getCollection()->map(fn(Referral $referral) => [
                'id' => $referral->id,
                'referrerName' => $referral->referrer?->name,
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
