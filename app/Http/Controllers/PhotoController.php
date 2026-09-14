<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhotoResource;
use App\Jobs\GeneratePhotoThumbnailJob;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\TemporaryUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PhotoController extends Controller
{
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

        $alreadySubmitted = Photo::where('competition_id', $competition->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($alreadySubmitted) {
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
