<?php

namespace App\Http\Services;

use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TemporaryUploadService extends Service
{
	/**
	 * Store a FilePond upload on the public disk and record it, so a later
	 * form submit by the same user can claim it by id.
	 */
	public function store(UploadedFile $file, User $user, string $directory = 'temporary-uploads'): TemporaryUpload
	{
		$temporaryUpload = new TemporaryUpload;
		$temporaryUpload->user_id = $user->id;
		$temporaryUpload->disk = 'public';
		$temporaryUpload->path = $file->store($directory, 'public');
		$temporaryUpload->original_name = $file->getClientOriginalName();
		$temporaryUpload->mime_type = $file->getClientMimeType();
		$temporaryUpload->size = $file->getSize();
		$temporaryUpload->save();

		return $temporaryUpload;
	}

	/**
	 * Delete one of the user's uploads and its file. Returns false if it
	 * was already gone (or was never theirs).
	 */
	public function destroy(User $user, int|string $id): bool
	{
		$temporaryUpload = TemporaryUpload::query()
			->ownedBy($user)
			->find($id);

		if (! $temporaryUpload) {
			return false;
		}

		Storage::disk($temporaryUpload->disk)->delete($temporaryUpload->path);
		$temporaryUpload->delete();

		return true;
	}
}
