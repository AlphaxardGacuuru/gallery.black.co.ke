<?php

namespace App\Listeners;

use App\Events\KopokopoTransferInitiated;
use App\Events\KopokopoTransferRecordedEvent;
use App\Http\Services\Service;
use App\Models\PhotoCompetitionWinner;
use App\Models\Referral;
use Illuminate\Support\Collection;

/**
 * A winner's/referrer's payout is only marked paid once Kopokopo's webhook
 * confirms the transfer actually completed, not when the disbursement
 * request was merely accepted (see
 * KopokopoTransferService::payWinner()/payReferrer(), which set
 * kopokopo_reference instead of prize_paid_at/paid_at at initiation time).
 * A confirmed failure clears that reference so the payout can be retried.
 * The recipient's "KES X has been sent" notification is deferred here too,
 * for the same reason: it shouldn't go out before the transfer is actually
 * confirmed.
 */
class KopokopoTransferRecordedListener
{
    public function handle(KopokopoTransferRecordedEvent $event): void
    {
        $transfer = $event->kopokopoTransfer;
        $reference = $transfer->kopokopo_id;

        if (! $reference) {
            return;
        }

        if ($transfer->isConfirmedSuccess()) {
            $this->confirmWinners($reference);
            $this->confirmReferrals($reference);

            return;
        }

        if ($transfer->isConfirmedFailure()) {
            PhotoCompetitionWinner::query()
                ->where('kopokopo_reference', $reference)
                ->whereNull('prize_paid_at')
                ->update(['kopokopo_reference' => null]);

            Referral::query()
                ->where('kopokopo_reference', $reference)
                ->whereNull('paid_at')
                ->update(['kopokopo_reference' => null, 'amount_paid' => null]);
        }
    }

    private function confirmWinners(string $reference): void
    {
        $winners = PhotoCompetitionWinner::query()
            ->with('user')
            ->where('kopokopo_reference', $reference)
            ->whereNull('prize_paid_at')
            ->get();

        /** @var PhotoCompetitionWinner $winner */
        foreach ($winners as $winner) {
            $winner->update(['prize_paid_at' => now()]);

            if ($winner->user?->phone) {
                KopokopoTransferInitiated::dispatch(
                    Service::normalizePhoneNumber($winner->user->phone),
                    (float) $winner->prize_amount,
                    $winner->user->name,
                    'Black Gallery weekly challenge prize (#' . $winner->position . ')',
                );
            }
        }
    }

    private function confirmReferrals(string $reference): void
    {
        /** @var Collection<int, Referral> $referrals */
        $referrals = Referral::query()
            ->with('referrer')
            ->where('kopokopo_reference', $reference)
            ->whereNull('paid_at')
            ->get();

        if ($referrals->isEmpty()) {
            return;
        }

        $referrals->each(fn(Referral $referral) => $referral->update(['paid_at' => now()]));

        $referrer = $referrals->first()->referrer;

        if ($referrer?->phone) {
            KopokopoTransferInitiated::dispatch(
                Service::normalizePhoneNumber($referrer->phone),
                (float) $referrals->sum('amount_paid'),
                $referrer->name,
                'Black Gallery referral reward',
            );
        }
    }
}
