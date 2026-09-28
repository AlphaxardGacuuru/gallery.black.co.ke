<?php

namespace App\Models;

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

    protected $fillable = [
        'user_id',
        'competition_id',
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
}
