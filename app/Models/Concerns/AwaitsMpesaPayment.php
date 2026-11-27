<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * For a row paid by an STK push and settled by Kopokopo's webhook. The
 * model defines STATUS_PENDING, STATUS_FAILED, STATUS_EXPIRED and
 * PENDING_TTL_MINUTES, and has status, failure_reason and created_at.
 */
trait AwaitsMpesaPayment
{
    /**
     * Pending AND not stale (see PENDING_TTL_MINUTES), a payment that's
     * still realistically awaiting the user's action on their phone, not
     * one abandoned minutes ago that should no longer block a retry.
     */
    public function scopeFreshPending(Builder $query): Builder
    {
        return $query
            ->where('status', static::STATUS_PENDING)
            ->where('created_at', '>=', now()->subMinutes(static::PENDING_TTL_MINUTES));
    }

    /**
     * The status shown to the payer while they wait: a pending payment
     * past PENDING_TTL_MINUTES reads as expired straight away, so their
     * "check your phone" spinner stops before ExpireStalePurchasesJob gets
     * to it.
     */
    public function statusForBuyer(): string
    {
        return $this->status === static::STATUS_PENDING
            && $this->created_at->lt(now()->subMinutes(static::PENDING_TTL_MINUTES))
            ? static::STATUS_EXPIRED
            : $this->status;
    }

    /**
     * Why the payment didn't go through, as one of a few codes the
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
            $this->statusForBuyer() === static::STATUS_EXPIRED => 'timed_out',
            $this->status !== static::STATUS_FAILED => null,
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
}
