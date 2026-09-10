<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Http\Services\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(protected OnboardingService $service) {}

    public function completePermissions(): JsonResponse
    {
        [$status, $message, $user] = $this->service->completePermissionsStep();

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => UserResource::make($user),
        ]);
    }
}
