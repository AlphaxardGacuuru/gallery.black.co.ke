<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TemporaryUpload extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'size' => 'integer',
    ];

    /**
     * Only the uploads this user made. Every claim or delete by id goes
     * through this, since the ids are sequential and easy to guess.
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }
}
