<?php

namespace App\Listeners;

use App\Events\KopokopoTransferInitiated;
use App\Events\KopokopoTransferRecordedEvent;
use App\Http\Services\Service;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoPurchase;
use App\Models\Referral;
use Illuminate\Support\Collection;

/**
 * A winner's, referrer's or photographer's payout is only marked paid once
 * Kopokopo's webhook confirms the transfer actually completed, not when the
 * disbursement request was merely accepted (see KopokopoTransferService's
 * payWinner()/payReferrer()/payPhotographer(), which set a reference
 * instead of prize_paid_at/paid_at/payout_paid_at at initiation time).
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
            $this->confirmPhotoSalePayouts($reference);

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

            Referral::query()
                ->where('photo_bonus_reference', $reference)
                ->whereNull('photo_bonus_paid_at')
                ->update(['photo_bonus_reference' => null, 'photo_bonus_amount' => null]);

            PhotoPurchase::query()
                ->where('payout_reference', $reference)
                ->whereNull('payout_paid_at')
                ->update(['payout_reference' => null]);
        }
    }

    private function confirmPhotoSalePayouts(string $reference): void
    {
        $purchases = PhotoPurchase::query()
            ->with('seller')
            ->where('payout_reference', $reference)
            ->whereNull('payout_paid_at')
            ->get();

        /** @var PhotoPurchase $purchase */
        foreach ($purchases as $purchase) {
            $purchase->update(['payout_paid_at' => now()]);

            if ($purchase->seller?->phone) {
                KopokopoTransferInitiated::dispatch(
                    Service::normalizePhoneNumber($purchase->seller->phone),
                    (float) $purchase->seller_amount,
                    $purchase->seller->name,
                    'Black Gallery photo sale',
                );
            }
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

    /**
     * One referrer transfer can cover batch rewards and photo bonuses at
     * once (see KopokopoTransferService::payReferrer()), so both are
     * confirmed together and the referrer gets a single notification for
     * the whole amount.
     */
    private function confirmReferrals(string $reference): void
    {
        /** @var Collection<int, Referral> $rewards */
        $rewards = Referral::query()
            ->with('referrer')
            ->where('kopokopo_reference', $reference)
            ->whereNull('paid_at')
            ->get();

        /** @var Collection<int, Referral> $photoBonuses */
        $photoBonuses = Referral::query()
            ->with('referrer')
            ->where('photo_bonus_reference', $reference)
            ->whereNull('photo_bonus_paid_at')
            ->get();

        if ($rewards->isEmpty() && $photoBonuses->isEmpty()) {
            return;
        }

        $rewards->each(fn(Referral $referral) => $referral->update(['paid_at' => now()]));
        $photoBonuses->each(fn(Referral $referral) => $referral->update(['photo_bonus_paid_at' => now()]));

        $referrer = ($rewards->first() ?? $photoBonuses->first())->referrer;

        if ($referrer?->phone) {
            KopokopoTransferInitiated::dispatch(
                Service::normalizePhoneNumber($referrer->phone),
                (float) $rewards->sum('amount_paid') + (float) $photoBonuses->sum('photo_bonus_amount'),
                $referrer->name,
                'Black Gallery referral reward',
            );
        }
    }
}
