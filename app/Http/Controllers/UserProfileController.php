<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhotoResource;
use App\Http\Services\PhotoPurchaseService;
use App\Http\Services\UserProfileService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function __construct(protected UserProfileService $service) {}

    /**
     * A user's profile: avatar, name and challenge stats.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->service->profile($user),
        ]);
    }

    /**
     * The grid of a user's posts on their profile.
     */
    public function photos(Request $request, User $user): JsonResponse
    {
        $photos = $this->service->photos($request, $user);

        return response()->json([
            'data' => PhotoResource::collection($photos),
            'sale' => PhotoPurchaseService::publicSaleSettings(),
            'meta' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
            ],
        ]);
    }
}
