<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Http\Services\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(protected OnboardingService $service) {}

    public function completeInstall(): JsonResponse
    {
        [$status, $message, $user] = $this->service->completeInstallStep();

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => UserResource::make($user),
        ]);
    }

    public function completePermissions(): JsonResponse
    {
        [$status, $message, $user] = $this->service->completePermissionsStep();

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => UserResource::make($user),
        ]);
    }

    public function completeReferral(): JsonResponse
    {
        [$status, $message, $user] = $this->service->completeReferralStep();

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => UserResource::make($user),
        ]);
    }
}
