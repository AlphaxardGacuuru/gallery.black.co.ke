<?php

namespace App\Models;

use App\Models\Concerns\AwaitsMpesaPayment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A small M-Pesa payment from a new phone number, proving it's a real
 * M-Pesa line the user controls. Paying it lifts the flag that changing
 * the number set (see User::FLAG_SOURCE_PHONE_CHANGE), as long as the
 * number paid from is still the one on the profile.
 */
class PhoneVerification extends Model
{
    use AwaitsMpesaPayment, HasUuids;

    public const STATUS_PENDING = PhotoSlotPurchase::STATUS_PENDING;

    public const STATUS_PAID = PhotoSlotPurchase::STATUS_PAID;

    public const STATUS_FAILED = PhotoSlotPurchase::STATUS_FAILED;

    public const STATUS_EXPIRED = PhotoSlotPurchase::STATUS_EXPIRED;

    public const UNSETTLED_STATUSES = PhotoSlotPurchase::UNSETTLED_STATUSES;

    public const PENDING_TTL_MINUTES = PhotoSlotPurchase::PENDING_TTL_MINUTES;

    protected $fillable = [
        'user_id',
        'phone',
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

    public function mpesaTransaction(): BelongsTo
    {
        return $this->belongsTo(MPESATransaction::class, 'mpesa_transaction_id');
    }
}
