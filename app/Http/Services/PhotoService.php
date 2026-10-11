<?php

namespace App\Http\Services;

use App\Jobs\GeneratePhotoImageThumbnailJob;
use App\Jobs\GeneratePhotoThumbnailJob;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoImage;
use App\Models\PhotoSlotPurchase;
use App\Models\TemporaryUpload;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
		$photo = Photo::with(['user', 'competition', 'images'])
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
	 * Submit a photo (already uploaded via FilePond) into the active
	 * competition. The first id becomes the entry's cover image (the one
	 * stored directly on Photo); any remaining ids become PhotoImage rows
	 * for the carousel, capped by Photo::maxImagesFor().
	 *
	 * @param  array{temporaryUploadIds: array<int, int|string>, caption: string}  $data
	 */
	public function store(Request $request, array $data): Photo
	{
		$competition = PhotoCompetition::active()->first();

		if (! $competition) {
			throw ValidationException::withMessages([
				'temporaryUploadIds' => 'There is no active competition right now.',
			]);
		}

		if (! $request->user()->phone) {
			throw ValidationException::withMessages([
				'temporaryUploadIds' => 'Add your M-Pesa phone number in your profile before submitting a photo.',
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
				'temporaryUploadIds' => 'Please try again in a moment.',
			]);
		}

		try {
			$hasPaidExtraSlot = PhotoSlotPurchase::ofType(PhotoSlotPurchase::TYPE_EXTRA_ENTRY)
				->where('competition_id', $competition->id)
				->where('user_id', $request->user()->id)
				->where('status', PhotoSlotPurchase::STATUS_PAID)
				->exists();

			$allowedSlots = $hasPaidExtraSlot ? 2 : 1;

			$submittedCount = Photo::where('competition_id', $competition->id)
				->where('user_id', $request->user()->id)
				->count();

			if ($submittedCount >= $allowedSlots) {
				throw ValidationException::withMessages([
					'temporaryUploadIds' => 'You\'ve already submitted a photo to this week\'s competition.',
				]);
			}

			$ids = $data['temporaryUploadIds'];
			$maxImages = Photo::maxImagesFor($competition->id, $request->user()->id);

			if (count($ids) > $maxImages) {
				throw ValidationException::withMessages([
					'temporaryUploadIds' => "You can attach up to {$maxImages} photo(s). Buy another photo slot to add more.",
				]);
			}

			$temporaryUploads = TemporaryUpload::whereIn('id', $ids)->get()->keyBy('id');

			if ($temporaryUploads->count() !== count($ids)) {
				throw ValidationException::withMessages([
					'temporaryUploadIds' => 'One of these uploads could not be found.',
				]);
			}

			$photo = DB::transaction(function () use ($ids, $temporaryUploads, $competition, $request, $data) {
				$coverUpload = $temporaryUploads[$ids[0]];
				$coverPath = $this->moveTemporaryUpload($coverUpload);
				[$width, $height] = $this->imageDimensions($coverPath);

				$photo = Photo::create([
					'competition_id' => $competition->id,
					'user_id' => $request->user()->id,
					'disk' => 'public',
					'path' => $coverPath,
					'caption' => $data['caption'] ?? null,
					'width' => $width,
					'height' => $height,
				]);

				foreach (array_slice($ids, 1) as $index => $id) {
					$this->attachImage($photo, $temporaryUploads[$id], $index + 1);
				}

				foreach ($temporaryUploads as $temporaryUpload) {
					$temporaryUpload->delete();
				}

				return $photo;
			});
		} finally {
			$lock->release();
		}

		// If a file move above succeeds but the transaction later rolls
		// back for an unrelated reason, the moved file(s) are orphaned
		// rather than cleaned up, acceptable here since it only risks a
		// stray file, never a dangling/inconsistent database row.
		GeneratePhotoThumbnailJob::dispatch($photo);

		foreach ($photo->images as $image) {
			GeneratePhotoImageThumbnailJob::dispatch($image);
		}

		return $photo->load(['user', 'images']);
	}

	/**
	 * Add more photos to an already-submitted entry (e.g. after buying an
	 * extra slot without wanting to delete and resubmit), while the
	 * competition is still active and within the owner's current
	 * Photo::maxImagesFor() cap.
	 *
	 * @param  array<int, int|string>  $ids
	 */
	public function addImages(Request $request, Photo $photo, array $ids): Photo
	{
		abort_unless($photo->user_id === $request->user()->id, 403);

		$photo->loadMissing('competition');

		if ($photo->competition->status !== PhotoCompetition::STATUS_ACTIVE) {
			throw ValidationException::withMessages([
				'temporaryUploadIds' => 'You can only add photos while the challenge is still active.',
			]);
		}

		$maxImages = Photo::maxImagesFor($photo->competition_id, $request->user()->id);
		$currentCount = 1 + $photo->images()->count();

		if ($currentCount + count($ids) > $maxImages) {
			throw ValidationException::withMessages([
				'temporaryUploadIds' => "You can attach up to {$maxImages} photo(s) total to this entry.",
			]);
		}

		$temporaryUploads = TemporaryUpload::whereIn('id', $ids)->get()->keyBy('id');

		if ($temporaryUploads->count() !== count($ids)) {
			throw ValidationException::withMessages([
				'temporaryUploadIds' => 'One of these uploads could not be found.',
			]);
		}

		$newImages = DB::transaction(function () use ($photo, $ids, $temporaryUploads) {
			$nextPosition = ($photo->images()->max('position') ?? 0) + 1;
			$created = [];

			foreach ($ids as $id) {
				$created[] = $this->attachImage($photo, $temporaryUploads[$id], $nextPosition);
				$nextPosition++;
			}

			foreach ($temporaryUploads as $temporaryUpload) {
				$temporaryUpload->delete();
			}

			return $created;
		});

		foreach ($newImages as $image) {
			GeneratePhotoImageThumbnailJob::dispatch($image);
		}

		return $photo->fresh(['user', 'images']);
	}

	private function attachImage(Photo $photo, TemporaryUpload $temporaryUpload, int $position): PhotoImage
	{
		$path = $this->moveTemporaryUpload($temporaryUpload);
		[$width, $height] = $this->imageDimensions($path);

		return PhotoImage::create([
			'photo_id' => $photo->id,
			'disk' => 'public',
			'path' => $path,
			'width' => $width,
			'height' => $height,
			'position' => $position,
		]);
	}

	private function moveTemporaryUpload(TemporaryUpload $temporaryUpload): string
	{
		$path = 'photos/' . basename($temporaryUpload->path);

		Storage::disk('public')->move($temporaryUpload->path, $path);

		return $path;
	}

	/**
	 * @return array{0: ?int, 1: ?int}
	 */
	private function imageDimensions(string $path): array
	{
		return getimagesize(Storage::disk('public')->path($path)) ?: [null, null];
	}

	/**
	 * Delete the authenticated user's own photo (while its competition is active).
	 */
	public function destroy(Request $request, string $id): void
	{
		$photo = Photo::where('user_id', $request->user()->id)
			->with(['competition', 'images'])
			->findOrFail($id);

		if ($photo->competition->status !== PhotoCompetition::STATUS_ACTIVE) {
			throw ValidationException::withMessages([
				'id' => 'You can only delete a photo while its challenge is still active.',
			]);
		}

		foreach ($photo->images as $image) {
			Storage::disk($image->disk)->delete(array_filter([$image->path, $image->thumbnail_path]));
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
