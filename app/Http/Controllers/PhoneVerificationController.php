<?php

namespace App\Http\Controllers;

use App\Http\Services\PhoneVerificationService;
use App\Models\PhoneVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneVerificationController extends Controller
{
    public function __construct(protected PhoneVerificationService $phoneVerificationService)
    {
        //
    }

    /**
     * Send the verification fee's STK push to the user's new phone number.
     * The frontend polls show() until Kopokopo's webhook settles it.
     */
    public function store(Request $request): JsonResponse
    {
        [$status, $message, $verification] = $this->phoneVerificationService->store($request);

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => ['verificationId' => $verification->id],
        ]);
    }

    /**
     * Poll a verification payment while the STK prompt is pending on the
     * user's phone.
     */
    public function show(Request $request, PhoneVerification $verification): JsonResponse
    {
        abort_unless($verification->user_id === $request->user()->id, 403);

        return response()->json([
            'data' => [
                'status' => $verification->statusForBuyer(),
                'failureReason' => $verification->failureReasonForBuyer(),
            ],
        ]);
    }
}
