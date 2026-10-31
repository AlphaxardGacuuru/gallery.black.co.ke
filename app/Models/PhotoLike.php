<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoLike extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoLikeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'photo_id',
        'user_id',
    ];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Likes real people gave to real people's photos, for admin stats. Seed
     * accounts' likes, and likes on their Unsplash entries, are left out.
     */
    public static function realCount(): int
    {
        return static::query()
            ->whereHas('user', fn($query) => $query->real())
            ->whereHas('photo', fn($query) => $query->real())
            ->count();
    }
}
