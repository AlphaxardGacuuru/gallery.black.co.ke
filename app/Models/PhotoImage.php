<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One of the extra images on a multi-photo entry (the cover image stays on
 * Photo itself, see Photo::images()). Mirrors Photo's own url/thumbnailUrl
 * accessors.
 */
class PhotoImage extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoImageFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'photo_id',
        'disk',
        'path',
        'thumbnail_path',
        'unsplash_id',
        'width',
        'height',
        'position',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'position' => 'integer',
    ];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->resolveUrl($this->path),
        );
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->resolveUrl($this->thumbnail_path ?? $this->path),
        );
    }

    /** Hotlinked seed images (Photo::DISK_REMOTE) store the full URL as their path. */
    private function resolveUrl(string $path): string
    {
        return $this->disk === Photo::DISK_REMOTE
            ? $path
            : Storage::disk($this->disk)->url($path);
    }
}
