<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

class GeneratePhotoThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The longest edge of the generated thumbnail, in pixels. Big enough to
     * stay sharp in the masonry Discover grid, small enough to noticeably
     * cut bandwidth versus the original upload.
     */
    private const MAX_DIMENSION = 1080;

    private const JPEG_QUALITY = 75;

    public function __construct(protected Photo $photo) {}

    /**
     * Downscale and compress the original upload into a grid-sized JPEG,
     * stored alongside it. Runs after the Photo row already exists so the
     * upload response never waits on image processing — Photo::thumbnailUrl
     * falls back to the original until this finishes.
     */
    public function handle(): void
    {
        $disk = Storage::disk($this->photo->disk);
        
        $originalPath = $disk->path($this->photo->path);

        $manager = ImageManager::usingDriver(GdDriver::class);

        $encoded = $manager->decodePath($originalPath)
            ->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION)
            ->encodeUsingFormat(Format::JPEG, quality: self::JPEG_QUALITY);

        $thumbnailPath = 'photos/thumbnails/'.pathinfo($this->photo->path, PATHINFO_FILENAME).'.jpg';

        $disk->put($thumbnailPath, $encoded->toString());

        $this->photo->update(['thumbnail_path' => $thumbnailPath]);
    }
}
