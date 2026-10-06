<?php

namespace App\Http\Controllers;

use App\Http\Services\PhotoSlotPurchaseService;
use App\Models\PhotoSlotPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoSlotPurchaseController extends Controller
{
    public function __construct(protected PhotoSlotPurchaseService $photoSlotPurchaseService)
    {
        //
    }

    /**
     * Start a Kopokopo STK push to buy this week's extra photo slot. The
     * purchase is recorded "pending" immediately and flips to "paid" async,
     * once Kopokopo's webhook confirms the payment (see
     * GrantPhotoSlotListener), the frontend polls show() in the meantime.
     */
    public function store(Request $request): JsonResponse
    {
        [$status, $message, $purchase] = $this->photoSlotPurchaseService->store($request);

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => ['purchaseId' => $purchase->id],
        ]);
    }

    /**
     * Poll a purchase's status while the STK prompt is pending on the
     * user's phone.
     */
    public function show(Request $request, PhotoSlotPurchase $purchase): JsonResponse
    {
        abort_unless($purchase->user_id === $request->user()->id, 403);

        return response()->json(['data' => ['status' => $purchase->status]]);
    }
}
