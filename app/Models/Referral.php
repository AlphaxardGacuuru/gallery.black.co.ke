<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'paid_at',
        'amount_paid',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount_paid' => 'decimal:2',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNull('paid_at');
    }

    /**
     * How much $referrerId is currently owed: every full $threshold-sized
     * group of their still-unpaid referrals splits $rewardAmount evenly
     * (e.g. a threshold of 5 at KES 50 pays KES 10 per referral). Partial
     * groups short of the threshold aren't paid yet.
     *
     * @return array{batches: int, referralIds: array<int, string>, totalAmount: float, perReferralAmount: float}
     */
    public static function eligiblePayout(
        string $referrerId,
        int $threshold,
        float $rewardAmount
    ): array {
        if ($threshold < 1) {
            return [
                'batches' => 0,
                'referralIds' => [],
                'totalAmount' => 0.0,
                'perReferralAmount' => 0.0
            ];
        }

        $unpaidIds = static::query()
            ->where('referrer_id', $referrerId)
            ->unpaid()
            ->oldest('created_at')
            ->pluck('id');

        $batches = intdiv($unpaidIds->count(), $threshold);

        return [
            'batches' => $batches,
            'referralIds' => $unpaidIds->take($batches * $threshold)->values()->all(),
            'totalAmount' => $batches * $rewardAmount,
            'perReferralAmount' => round($rewardAmount / $threshold, 2),
        ];
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
