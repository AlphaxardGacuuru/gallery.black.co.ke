<?php

namespace App\Http\Controllers;

use App\Http\Services\ReferralService;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(protected ReferralService $referralService)
    {
        //
    }

    /**
     * The current referral reward threshold/amount, so any signed-in user
     * (not just admins) can see what referring friends earns them.
     */
    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => $this->referralService->settings(),
        ]);
    }

    /**
     * The authenticated user's own referral history, most recent first.
     */
    public function mine(Request $request): JsonResponse
    {
        $referrals = $this->referralService->mine($request);

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
