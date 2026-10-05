<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhotoResource;
use App\Jobs\GeneratePhotoThumbnailJob;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use App\Models\TemporaryUpload;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PhotoController extends Controller
{
    /**
     * A single photo's full-size view (any competitor's, active or ended),
     * plus this viewer's permissions for it, since those depend on whether
     * its competition is still active.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $photo = Photo::with(['user', 'competition'])
            ->withLikedByViewer($request->user())
            ->withIsWinner()
            ->findOrFail($id);

        $isActive = $photo->competition->status === PhotoCompetition::STATUS_ACTIVE;

        return response()->json([
            'data' => new PhotoResource($photo),
            'canDelete' => $isActive && $photo->user_id === $request->user()->id,
            'canLike' => $isActive,
        ]);
    }

    /**
     * Submit a photo (already uploaded via FilePond) into the active competition.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'temporaryUploadId' => 'required|exists:temporary_uploads,id',
            'caption' => 'required|string|max:280',
        ]);

        $competition = PhotoCompetition::active()->first();

        if (! $competition) {
            throw ValidationException::withMessages([
                'temporaryUploadId' => 'There is no active competition right now.',
            ]);
        }

        if (! $request->user()->phone) {
            throw ValidationException::withMessages([
                'temporaryUploadId' => 'Add your M-Pesa phone number in your profile before submitting a photo.',
            ]);
        }

        // The slot-count check below and the insert that follows it aren't
        // atomic on their own (there's no longer a DB-level unique
        // constraint to fall back on, see
        // 2026_09_28_120001_drop_unique_constraint_from_photos_table), so
        // two requests from the same user (two tabs, a network retry)
        // could otherwise both pass the check before either row exists.
        // This lock serializes them per user per competition instead.
        $lock = Cache::lock("photo-submission:{$competition->id}:{$request->user()->id}", 10);

        try {
            $lock->block(2);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'temporaryUploadId' => 'Please try again in a moment.',
            ]);
        }

        try {
            $hasPaidExtraSlot = PhotoSlotPurchase::where('competition_id', $competition->id)
                ->where('user_id', $request->user()->id)
                ->where('status', PhotoSlotPurchase::STATUS_PAID)
                ->exists();

            $allowedSlots = $hasPaidExtraSlot ? 2 : 1;

            $submittedCount = Photo::where('competition_id', $competition->id)
                ->where('user_id', $request->user()->id)
                ->count();

            if ($submittedCount >= $allowedSlots) {
                throw ValidationException::withMessages([
                    'temporaryUploadId' => 'You\'ve already submitted a photo to this week\'s competition.',
                ]);
            }

            $temporaryUpload = TemporaryUpload::findOrFail($data['temporaryUploadId']);

            $path = 'photos/' . basename($temporaryUpload->path);

            Storage::disk('public')->move($temporaryUpload->path, $path);

            [$width, $height] = getimagesize(Storage::disk('public')->path($path)) ?: [null, null];

            $photo = Photo::create([
                'competition_id' => $competition->id,
                'user_id' => $request->user()->id,
                'disk' => 'public',
                'path' => $path,
                'caption' => $data['caption'] ?? null,
                'width' => $width,
                'height' => $height,
            ]);

            $temporaryUpload->delete();
        } finally {
            $lock->release();
        }

        GeneratePhotoThumbnailJob::dispatch($photo);

        return response()->json([
            'data' => new PhotoResource($photo->load('user')),
        ], 201);
    }

    /**
     * Delete the authenticated user's own photo (while its competition is active).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $photo = Photo::where('user_id', $request->user()->id)
            ->with('competition')
            ->findOrFail($id);

        if ($photo->competition->status !== PhotoCompetition::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'id' => 'You can only delete a photo while its challenge is still active.',
            ]);
        }

        Storage::disk($photo->disk)->delete(array_filter([$photo->path, $photo->thumbnail_path]));
        $photo->delete();

        return response()->json(['data' => null]);
    }
}
