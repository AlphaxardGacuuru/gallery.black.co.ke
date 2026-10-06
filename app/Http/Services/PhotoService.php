<?php

namespace App\Http\Services;

use App\Jobs\GeneratePhotoThumbnailJob;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use App\Models\TemporaryUpload;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PhotoService extends Service
{
	/**
	 * A single photo (any competitor's, active or ended), plus this viewer's
	 * permissions for it, since those depend on whether its competition is
	 * still active.
	 *
	 * @return array{photo: Photo, canDelete: bool, canLike: bool}
	 */
	public function show(Request $request, string $id): array
	{
		$photo = Photo::with(['user', 'competition'])
			->withLikedByViewer($request->user())
			->withIsWinner()
			->findOrFail($id);

		$isActive = $photo->competition->status === PhotoCompetition::STATUS_ACTIVE;

		return [
			'photo' => $photo,
			'canDelete' => $isActive && $photo->user_id === $request->user()->id,
			'canLike' => $isActive,
		];
	}

	/**
	 * Submit a photo (already uploaded via FilePond) into the active competition.
	 *
	 * @param  array{temporaryUploadId: string, caption: string}  $data
	 */
	public function store(Request $request, array $data): Photo
	{
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

		return $photo->load('user');
	}

	/**
	 * Delete the authenticated user's own photo (while its competition is active).
	 */
	public function destroy(Request $request, string $id): void
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
	}

	/**
	 * Like a photo on the authenticated user's behalf. A like is permanent:
	 * liking a photo the user already liked is a no-op, not an unlike, so
	 * it's safe to call idempotently (e.g. a retried or duplicate request
	 * never decrements likes_count).
	 *
	 * @return array{photo: Photo, liked: bool} liked is true only when this call added a new like
	 */
	public function like(Request $request, string $id): array
	{
		$photo = Photo::with('competition')->findOrFail($id);
		$user = $request->user();

		if ($photo->competition->status !== PhotoCompetition::STATUS_ACTIVE) {
			throw ValidationException::withMessages([
				'id' => 'Likes are frozen once a challenge ends.',
			]);
		}

		if ($photo->likes()->where('user_id', $user->id)->exists()) {
			return ['photo' => $photo, 'liked' => false];
		}

		$photo->likes()->create(['user_id' => $user->id]);
		$photo->increment('likes_count');
		$photo->refresh();

		return ['photo' => $photo, 'liked' => true];
	}
}
