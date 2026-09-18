<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
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
