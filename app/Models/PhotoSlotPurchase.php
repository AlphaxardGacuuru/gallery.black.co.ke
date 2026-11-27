<?php

namespace App\Models;

use App\Models\Concerns\AwaitsMpesaPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoSlotPurchase extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoSlotPurchaseFactory> */
    use AwaitsMpesaPayment, HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    /**
     * Never confirmed either way, so written off by ExpireStalePurchasesJob.
     * Unlike "failed" it can still turn "paid" if a late webhook arrives
     * (see GrantPhotoSlotListener), since the buyer may have paid after all.
     */
    public const STATUS_EXPIRED = 'expired';

    /** Statuses a payment webhook may still settle, paid or failed. */
    public const UNSETTLED_STATUSES = [self::STATUS_PENDING, self::STATUS_EXPIRED];

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
     * purchase attempt forever. Stale pending rows are only ignored here;
     * ExpireStalePurchasesJob later marks them expired, which a late
     * webhook can still turn into paid.
     */
    public const PENDING_TTL_MINUTES = 3;

    /** How long a pending purchase sits before ExpireStalePurchasesJob marks it expired. */
    public const EXPIRE_AFTER_MINUTES = 15;

    protected $fillable = [
        'user_id',
        'competition_id',
        'type',
        'amount',
        'status',
        'failure_reason',
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
