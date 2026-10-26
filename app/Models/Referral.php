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
        'kopokopo_reference',
        'photo_bonus_amount',
        'photo_bonus_reference',
        'photo_bonus_paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount_paid' => 'decimal:2',
        'photo_bonus_amount' => 'decimal:2',
        'photo_bonus_paid_at' => 'datetime',
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
     * Not yet paid, and not already in the middle of a payout attempt
     * (kopokopo_reference is set between initiating a transfer and
     * Kopokopo's webhook confirming it, see
     * KopokopoTransferService::payReferrer()), otherwise the same batch
     * could be paid out twice while the first transfer is still pending.
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNull('paid_at')->whereNull('kopokopo_reference');
    }

    /**
     * Referrals whose referred user has posted at least one photo and whose
     * photo bonus hasn't been paid or started paying yet (same in-flight
     * rule as scopeUnpaid(), via photo_bonus_reference).
     */
    public function scopeUnpaidPhotoBonus(Builder $query): Builder
    {
        return $query
            ->whereNull('photo_bonus_paid_at')
            ->whereNull('photo_bonus_reference')
            ->whereHas('referred.photos');
    }

    /**
     * Annotate each referral with whether the referred user has posted a
     * photo yet (referred_has_posted), via a correlated exists() subquery.
     */
    public function scopeWithReferredHasPosted(Builder $query): Builder
    {
        if (is_null($query->getQuery()->columns)) {
            $query->select('referrals.*');
        }

        return $query->addSelect([
            'referred_has_posted' => Photo::query()
                ->selectRaw('1')
                ->whereColumn('photos.user_id', 'referrals.referred_id')
                ->limit(1),
        ]);
    }

    /**
     * How much $referrerId is currently owed: every full $threshold-sized
     * group of their still-unpaid referrals splits $rewardAmount evenly
     * (e.g. a threshold of 5 at KES 50 pays KES 10 per referral). Partial
     * groups short of the threshold aren't paid yet. On top of that, each
     * referral whose referred user has posted a photo earns a one-off
     * $photoBonusAmount, with no threshold.
     *
     * @return array{batches: int, referralIds: array<int, string>, totalAmount: float, perReferralAmount: float, photoBonusReferralIds: array<int, string>, photoBonusAmount: float}
     */
    public static function eligiblePayout(
        string $referrerId,
        int $threshold,
        float $rewardAmount,
        float $photoBonusAmount = 0.0
    ): array {
        $photoBonusIds = $photoBonusAmount > 0
            ? static::query()
                ->where('referrer_id', $referrerId)
                ->unpaidPhotoBonus()
                ->oldest('created_at')
                ->pluck('id')
                ->all()
            : [];

        $photoBonusTotal = count($photoBonusIds) * $photoBonusAmount;

        if ($threshold < 1) {
            return [
                'batches' => 0,
                'referralIds' => [],
                'totalAmount' => $photoBonusTotal,
                'perReferralAmount' => 0.0,
                'photoBonusReferralIds' => $photoBonusIds,
                'photoBonusAmount' => $photoBonusAmount,
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
            'totalAmount' => $batches * $rewardAmount + $photoBonusTotal,
            'perReferralAmount' => round($rewardAmount / $threshold, 2),
            'photoBonusReferralIds' => $photoBonusIds,
            'photoBonusAmount' => $photoBonusAmount,
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

    /**
     * Headline referral numbers, shared by the admin referrals page and the
     * admin dashboard so the two can't disagree. Payouts only count as paid
     * once Kopokopo's webhook confirms them (paid_at/photo_bonus_paid_at);
     * ones initiated but not yet confirmed are "pending". Both include
     * photo bonuses.
     *
     * @return array{totalReferrals: int, totalReferrers: int, totalPaidOut: int, totalPendingPayout: int}
     */
    public static function summary(): array
    {
        return [
            'totalReferrals' => static::query()->count(),
            'totalReferrers' => static::query()->distinct()->count('referrer_id'),
            'totalPaidOut' => (int) round(
                (float) static::query()->whereNotNull('paid_at')->sum('amount_paid')
                + (float) static::query()->whereNotNull('photo_bonus_paid_at')->sum('photo_bonus_amount')
            ),
            'totalPendingPayout' => (int) round(
                (float) static::query()
                    ->whereNull('paid_at')
                    ->whereNotNull('kopokopo_reference')
                    ->sum('amount_paid')
                + (float) static::query()
                    ->whereNull('photo_bonus_paid_at')
                    ->whereNotNull('photo_bonus_reference')
                    ->sum('photo_bonus_amount')
            ),
        ];
    }
}
