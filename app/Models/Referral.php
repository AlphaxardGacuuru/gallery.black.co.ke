<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'referrer_id',
        'referred_id',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Credit $referrerId with referring $referred, unless it's missing,
     * self-referral, the referrer doesn't exist, or $referred is already
     * credited to someone (each user can only ever be referred once).
     */
    public static function record(?string $referrerId, User $referred): void
    {
        if (! $referrerId || $referrerId === $referred->id) {
            return;
        }

        if (! User::query()->whereKey($referrerId)->exists()) {
            return;
        }

        static::query()->firstOrCreate(
            ['referred_id' => $referred->id],
            ['referrer_id' => $referrerId]
        );
    }
}
