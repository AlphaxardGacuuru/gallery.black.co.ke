<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GeneratePhotoImageThumbnailJob;
use App\Models\PhotoImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeneratePhotoImageThumbnailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_writes_a_thumbnail_and_updates_the_photo_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.jpg', 1200, 1200);
        $path = 'photos/original.jpg';
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $photoImage = PhotoImage::factory()->create([
            'path' => $path,
            'thumbnail_path' => null,
        ]);

        (new GeneratePhotoImageThumbnailJob($photoImage))->handle();

        $photoImage->refresh();
        $this->assertNotNull($photoImage->thumbnail_path);
        $this->assertTrue(Storage::disk('public')->exists($photoImage->thumbnail_path));
        $this->assertStringStartsWith('photo-images/thumbnails/', $photoImage->thumbnail_path);
    }
}
