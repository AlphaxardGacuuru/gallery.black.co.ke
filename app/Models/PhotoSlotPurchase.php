<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PhotoSlotPurchase extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoSlotPurchaseFactory> */
    use HasFactory, HasUuids;

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

    /**
     * The status shown to the buyer while they wait: a pending purchase
     * past PENDING_TTL_MINUTES reads as expired straight away, so their
     * "check your phone" spinner stops before the job gets to it.
     */
    public function statusForBuyer(): string
    {
        return $this->status === self::STATUS_PENDING
            && $this->created_at->lt(now()->subMinutes(self::PENDING_TTL_MINUTES))
            ? self::STATUS_EXPIRED
            : $this->status;
    }

    /**
     * Why the buyer's payment didn't go through, as one of a few codes the
     * frontend turns into a message (see paymentFailureMessage() in
     * queries/photos.ts), or null while it hasn't failed. The match is on
     * the M-Pesa result descriptions Kopokopo passes along, which have been
     * seen in production ("Request Cancelled by user.", "DS timeout user
     * cannot be reached.") or in its sandbox ("The balance is insufficient
     * for the transaction.").
     */
    public function failureReasonForBuyer(): ?string
    {
        return match (true) {
            $this->statusForBuyer() === self::STATUS_EXPIRED => 'timed_out',
            $this->status !== self::STATUS_FAILED => null,
            default => Str::of((string) $this->failure_reason)
                ->lower()
                ->pipe(fn ($reason) => match (true) {
                    $reason->contains('cancelled') => 'cancelled',
                    $reason->contains('cannot be reached') => 'unreachable',
                    $reason->contains('insufficient') => 'insufficient_funds',
                    $reason->contains('initiator information is invalid') => 'wrong_pin',
                    $reason->contains('timeout') => 'timed_out',
                    default => 'unknown',
                }),
        };
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
