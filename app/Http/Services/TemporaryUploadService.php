<?php

namespace App\Http\Services;

use App\Models\TemporaryUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TemporaryUploadService extends Service
{
	/**
	 * Store a FilePond upload on the public disk and record it, so a later
	 * form submit can claim it by id.
	 */
	public function store(UploadedFile $file, string $directory = 'temporary-uploads'): TemporaryUpload
	{
		$temporaryUpload = new TemporaryUpload;
		$temporaryUpload->disk = 'public';
		$temporaryUpload->path = $file->store($directory, 'public');
		$temporaryUpload->original_name = $file->getClientOriginalName();
		$temporaryUpload->mime_type = $file->getClientMimeType();
		$temporaryUpload->size = $file->getSize();
		$temporaryUpload->save();

		return $temporaryUpload;
	}

	/**
	 * Delete an upload and its file. Returns false if it was already gone.
	 */
	public function destroy(int|string $id): bool
	{
		$temporaryUpload = TemporaryUpload::find($id);

		if (! $temporaryUpload) {
			return false;
		}

		Storage::disk($temporaryUpload->disk)->delete($temporaryUpload->path);
		$temporaryUpload->delete();

		return true;
	}
}
