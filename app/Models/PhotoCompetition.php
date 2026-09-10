<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoCompetition extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoCompetitionFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'starts_at',
        'ends_at',
        'status',
        'prize_amount',
        'winner_photo_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'prize_amount' => 'integer',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'competition_id');
    }

    public function winnerPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'winner_photo_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
