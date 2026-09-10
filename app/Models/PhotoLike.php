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
}
