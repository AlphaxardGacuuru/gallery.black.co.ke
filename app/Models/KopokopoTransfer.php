<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KopokopoTransfer extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_WINNINGS = 'winnings';

    /**
     * Referral rewards and the bonus paid when a referred user's photo sells.
     */
    public const TYPE_REFERRALS = 'referrals';

    public const TYPE_PHOTOGRAPHERS = 'photographers';

    /**
     * Money sent from the admin "send money" form, or a transfer whose
     * payout can't be found.
     */
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_WINNINGS,
        self::TYPE_REFERRALS,
        self::TYPE_PHOTOGRAPHERS,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'user_id',
        'kopokopo_id',
        'kopokopo_location',
        'kopokopo_created_at',
        'amount',
        'recipient_name',
        'recipient_phone',
        'currency',
        'status',
        'errors',
        'transfer_batches',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'transfer_batches' => 'array',
        'errors' => 'array',
        'metadata' => 'array',
        'updated_at' => 'datetime:d M Y',
        'created_at' => 'datetime:d M Y',
    ];

    protected function kopokopoCreatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    protected function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    /*
     * Relationships
     */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /*
     * Scopes
     */

    /**
     * Transfers that paid one kind of payout. Each payout records the
     * transfer's Kopokopo id as its reference when it's sent, so that's
     * what links them (see KopokopoTransferService).
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        $references = self::payoutReferences();

        if ($type !== self::TYPE_OTHER) {
            return $query->where(function (Builder $query) use ($references, $type) {
                collect($references[$type])
                    ->each(fn (Builder $subquery) => $query->orWhereIn('kopokopo_id', $subquery));
            });
        }

        return $query->where(function (Builder $query) use ($references) {
            $query->whereNull('kopokopo_id')
                ->orWhere(function (Builder $query) use ($references) {
                    collect($references)
                        ->flatten()
                        ->each(fn (Builder $subquery) => $query->whereNotIn('kopokopo_id', $subquery));
                });
        });
    }

    /**
     * For each payout type, the queries selecting the Kopokopo ids of the
     * transfers that paid it. Nulls are left out so NOT IN still matches.
     *
     * @return array<string, list<Builder>>
     */
    private static function payoutReferences(): array
    {
        return [
            self::TYPE_WINNINGS => [
                PhotoCompetitionWinner::query()
                    ->whereNotNull('kopokopo_reference')
                    ->select('kopokopo_reference'),
            ],
            self::TYPE_REFERRALS => [
                Referral::query()
                    ->whereNotNull('kopokopo_reference')
                    ->select('kopokopo_reference'),
                Referral::query()
                    ->whereNotNull('photo_bonus_reference')
                    ->select('photo_bonus_reference'),
            ],
            self::TYPE_PHOTOGRAPHERS => [
                PhotoPurchase::query()
                    ->whereNotNull('payout_reference')
                    ->select('payout_reference'),
            ],
        ];
    }

    /**
     * Kopokopo's send-money status isn't a fixed enum we can exhaustively
     * match, so this also checks the per-disbursement status.
     */
    public function isConfirmedSuccess(): bool
    {
        return (bool) preg_match('/process|transfer|complet|success/i', (string) $this->status)
            || $this->hasOnlyTransferredDisbursements();
    }

    /**
     * A transfer Kopokopo rejects as a whole (e.g. insufficient balance)
     * can come back failed with no disbursements at all, so the top-level
     * status decides then. Otherwise every disbursement must have failed.
     */
    public function isConfirmedFailure(): bool
    {
        if ($this->hasOnlyTransferredDisbursements()) {
            return false;
        }

        if ($this->disbursements()->isEmpty()) {
            return (bool) preg_match('/fail|reject|declin|error|cancel/i', (string) $this->status);
        }

        return $this->hasOnlyFailedDisbursements();
    }

    private function disbursements()
    {
        return collect($this->transfer_batches ?? [])
            ->flatMap(fn ($batch) => $batch['disbursements'] ?? []);
    }

    public function hasOnlyTransferredDisbursements(): bool
    {
        $disbursements = $this->disbursements();

        return $disbursements->isNotEmpty()
            && $disbursements->every(fn ($disbursement) => strcasecmp((string) ($disbursement['status'] ?? ''), 'Transferred') === 0);
    }

    private function hasOnlyFailedDisbursements(): bool
    {
        $disbursements = $this->disbursements();

        return $disbursements->isNotEmpty()
            && $disbursements->every(fn ($disbursement) => (bool) preg_match('/fail|reject|declin|error/i', (string) ($disbursement['status'] ?? '')));
    }
}
