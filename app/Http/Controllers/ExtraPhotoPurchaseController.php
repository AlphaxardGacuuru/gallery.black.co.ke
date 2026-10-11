<?php

namespace App\Http\Controllers;

use App\Http\Services\ExtraPhotoPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtraPhotoPurchaseController extends Controller
{
    public function __construct(protected ExtraPhotoPurchaseService $extraPhotoPurchaseService)
    {
        //
    }

    /**
     * Start a Kopokopo STK push to buy one additional photo slot. Polling
     * for confirmation reuses PhotoSlotPurchaseController::show(), which is
     * generic by purchase id regardless of type.
     */
    public function store(Request $request): JsonResponse
    {
        [$status, $message, $purchase] = $this->extraPhotoPurchaseService->store($request);

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => ['purchaseId' => $purchase->id],
        ]);
    }
}
