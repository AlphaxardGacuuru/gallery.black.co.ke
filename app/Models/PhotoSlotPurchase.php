<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoSlotPurchase extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoSlotPurchaseFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    /** A second, independent competition entry. */
    public const TYPE_EXTRA_ENTRY = 'extra_entry';

    /** One additional photo slot on a single entry's carousel. */
    public const TYPE_EXTRA_PHOTO = 'extra_photo';

    /**
     * A purchase only leaves "pending" when a webhook says so: "paid" via
     * GrantPhotoSlotListener, or "failed" via MpesaPaymentFailedListener
     * when Kopokopo reports the prompt was cancelled or failed. If neither
     * webhook ever arrives (the user ignores the prompt and Kopokopo stays
     * silent), a single abandoned prompt would otherwise block every future
     * purchase attempt forever. Stale pending rows are never themselves
     * marked failed, only ignored, since a late webhook would otherwise
     * confirm a payment that was already written off.
     */
    public const PENDING_TTL_MINUTES = 3;

    protected $fillable = [
        'user_id',
        'competition_id',
        'type',
        'amount',
        'status',
        'kopokopo_reference',
        'mpesa_transaction_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(PhotoCompetition::class, 'competition_id');
    }

    public function mpesaTransaction(): BelongsTo
    {
        return $this->belongsTo(MPESATransaction::class, 'mpesa_transaction_id');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Pending AND not stale (see PENDING_TTL_MINUTES), a purchase that's
     * still realistically awaiting the user's action on their phone, not
     * one abandoned minutes ago that should no longer block a retry.
     */
    public function scopeFreshPending(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->where('created_at', '>=', now()->subMinutes(self::PENDING_TTL_MINUTES));
    }

    public static function paidExtraPhotoCount(string $competitionId, string $userId): int
    {
        return static::query()
            ->ofType(self::TYPE_EXTRA_PHOTO)
            ->where('competition_id', $competitionId)
            ->where('user_id', $userId)
            ->where('status', self::STATUS_PAID)
            ->count();
    }
}
