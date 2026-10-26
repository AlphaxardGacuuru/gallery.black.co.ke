<?php

namespace App\Http\Controllers;

use App\Http\Services\PhotoPurchaseService;
use App\Models\Photo;
use App\Models\PhotoImage;
use App\Models\PhotoPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoPurchaseController extends Controller
{
    public function __construct(protected PhotoPurchaseService $photoPurchaseService)
    {
        //
    }

    /**
     * Start a Kopokopo STK push to buy a full-resolution copy of a photo.
     * The frontend polls show() until the webhook confirms or fails it.
     */
    public function store(Request $request, Photo $photo): JsonResponse
    {
        [$status, $message, $purchase] = $this->photoPurchaseService->store($request, $photo);

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => ['purchaseId' => $purchase->id],
        ]);
    }

    /**
     * Poll a purchase's status while the STK prompt is pending on the
     * buyer's phone.
     */
    public function show(Request $request, PhotoPurchase $purchase): JsonResponse
    {
        abort_unless($purchase->buyer_id === $request->user()->id, 403);

        return response()->json(['data' => ['status' => $purchase->status]]);
    }

    /**
     * Fresh signed download links for every original in the entry, for a
     * buyer (or the photographer themselves).
     */
    public function downloads(Request $request, Photo $photo): JsonResponse
    {
        abort_unless($this->photoPurchaseService->canDownload($request->user(), $photo), 403);

        return response()->json(['data' => $this->photoPurchaseService->downloadLinks($photo)]);
    }

    /**
     * Stream one original image as a file download. Reached through a
     * temporary signed URL from downloads(), so it needs no auth of its own.
     */
    public function download(Photo $photo, ?PhotoImage $image = null): StreamedResponse
    {
        abort_if($image && $image->photo_id !== $photo->id, 404);

        $source = $image ?? $photo;
        $number = $image ? $image->position + 1 : 1;
        $extension = pathinfo($source->path, PATHINFO_EXTENSION) ?: 'jpg';

        return Storage::disk($source->disk)->download(
            $source->path,
            "black-gallery-{$photo->id}-{$number}.{$extension}"
        );
    }
}
