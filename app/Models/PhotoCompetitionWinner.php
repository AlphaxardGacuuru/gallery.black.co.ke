<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoCompetitionWinner extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoCompetitionWinnerFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'competition_id',
        'photo_id',
        'user_id',
        'position',
        'prize_amount',
        'prize_paid_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'prize_amount' => 'integer',
        'prize_paid_at' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(PhotoCompetition::class, 'competition_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
