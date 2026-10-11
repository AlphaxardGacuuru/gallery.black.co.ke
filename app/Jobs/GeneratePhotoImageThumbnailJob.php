<?php

namespace App\Jobs;

use App\Models\PhotoImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

/**
 * Same downscale-and-compress treatment as GeneratePhotoThumbnailJob, for
 * one of a multi-photo entry's extra images (see Photo::images()).
 */
class GeneratePhotoImageThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_DIMENSION = 1080;

    private const JPEG_QUALITY = 75;

    public function __construct(protected PhotoImage $photoImage) {}

    public function handle(): void
    {
        $disk = Storage::disk($this->photoImage->disk);

        $originalPath = $disk->path($this->photoImage->path);

        $manager = ImageManager::usingDriver(GdDriver::class);

        $encoded = $manager->decodePath($originalPath)
            ->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION)
            ->encodeUsingFormat(Format::JPEG, quality: self::JPEG_QUALITY);

        $thumbnailPath = 'photo-images/thumbnails/'.pathinfo($this->photoImage->path, PATHINFO_FILENAME).'.jpg';

        $disk->put($thumbnailPath, $encoded->toString());

        $this->photoImage->update(['thumbnail_path' => $thumbnailPath]);
    }
}
