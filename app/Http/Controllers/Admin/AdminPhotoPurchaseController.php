<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\KopokopoTransferService;
use App\Models\PhotoPurchase;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPhotoPurchaseController extends Controller
{
    public function __construct(protected KopokopoTransferService $kopokopoTransferService)
    {
        //
    }

    /**
     * Paginated history of photo purchase attempts (pending, paid and
     * failed), most recent first, with where each photographer payout is.
     */
    public function index(Request $request): JsonResponse
    {
        $purchases = PhotoPurchase::query()
            ->with(['buyer', 'seller', 'photo'])
            ->latest('created_at')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $purchases->getCollection()->map(fn(PhotoPurchase $purchase) => [
                'id' => $purchase->id,
                'photoId' => $purchase->photo_id,
                'photoThumbnailUrl' => $purchase->photo?->thumbnail_url,
                'buyerName' => $purchase->buyer?->name,
                'buyerPhone' => $purchase->buyer?->phone,
                'sellerName' => $purchase->seller?->name,
                'sellerPhone' => $purchase->seller?->phone,
                'amount' => $purchase->amount,
                'sellerAmount' => $purchase->seller_amount,
                'status' => $purchase->status,
                'createdAt' => $purchase->created_at,
                'paidAt' => $purchase->paid_at,
                'payoutReference' => $purchase->payout_reference,
                'payoutPaidAt' => $purchase->payout_paid_at,
            ])->values(),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    /**
     * Toggle photo sales, set the KES price, and the percentage of it that
     * goes to the photographer.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'price' => 'required|integer|min:0',
            'photographerShare' => 'required|integer|min:0|max:100',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'photo_sale'],
            ['value' => $data]
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Retry a photographer's payout that didn't start automatically when
     * the sale landed (e.g. they had no phone number yet, or Kopokopo
     * rejected the request).
     */
    public function pay(PhotoPurchase $purchase): JsonResponse
    {
        [$status, $message, $data] = $this->kopokopoTransferService->payPhotographer($purchase);

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $status ? ['payoutReference' => $purchase->fresh()->payout_reference] : $data,
        ]);
    }
}
