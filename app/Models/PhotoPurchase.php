<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt by a user to buy a full-resolution copy of a photo. The buyer
 * pays the full amount via a Kopokopo STK push, and once that's confirmed
 * the photographer's share (seller_amount) is sent to them via a Kopokopo
 * transfer, tracked separately by payout_reference/payout_paid_at.
 */
class PhotoPurchase extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoPurchaseFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'photo_id',
        'buyer_id',
        'seller_id',
        'amount',
        'seller_amount',
        'status',
        'kopokopo_reference',
        'mpesa_transaction_id',
        'paid_at',
        'payout_reference',
        'payout_paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'seller_amount' => 'integer',
        'paid_at' => 'datetime',
        'payout_paid_at' => 'datetime',
    ];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function mpesaTransaction(): BelongsTo
    {
        return $this->belongsTo(MPESATransaction::class, 'mpesa_transaction_id');
    }

    /**
     * Pending AND not stale, same rule as PhotoSlotPurchase::scopeFreshPending()
     * so an abandoned STK prompt doesn't block buying the photo forever.
     */
    public function scopeFreshPending(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->where('created_at', '>=', now()->subMinutes(PhotoSlotPurchase::PENDING_TTL_MINUTES));
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }
}
