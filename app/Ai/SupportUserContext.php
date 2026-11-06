<?php

namespace App\Ai;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoPurchase;
use App\Models\PhotoSlotPurchase;
use App\Models\Referral;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * What the support assistant may know about the customer it's talking to:
 * their own account and payments only, read-only, as plain text for the
 * prompt. Recent records only, to keep the prompt (and the bill) small.
 */
class SupportUserContext
{
    private const RECENT = 5;

    public function __construct(protected User $user) {}

    public function render(): string
    {
        return implode("\n\n", array_filter([
            $this->account(),
            $this->currentCompetition(),
            $this->entryPayments(),
            $this->photosBought(),
            $this->photosSold(),
            $this->wins(),
            $this->referrals(),
        ]));
    }

    private function account(): string
    {
        $user = $this->user;

        return implode("\n", [
            '## Account',
            "- Name: {$user->name}",
            // created_at has a display accessor on User, so read the raw value.
            '- Joined: '.$this->date($user->getRawOriginal('created_at')),
            '- M-Pesa phone number on profile: '.($user->phone ? 'yes' : 'no (needed to receive payouts)'),
            '- Verified badge: '.($user->verified ? 'yes' : 'no'),
            '- Account flagged: '.match (true) {
                ! $user->isFlagged() => 'no',
                $user->flag_refuted_at !== null => 'yes, and they updated their phone number to dispute it (waiting for review)',
                default => 'yes',
            },
        ]);
    }

    private function currentCompetition(): string
    {
        $competition = PhotoCompetition::active()->latest('starts_at')->first();

        if (! $competition) {
            return "## Current photo challenge\n- None is running right now.";
        }

        $entries = Photo::where('competition_id', $competition->id)
            ->where('user_id', $this->user->id)
            ->first();

        return implode("\n", [
            '## Current photo challenge',
            '- Runs '.$this->date($competition->starts_at).' to '.$this->date($competition->ends_at),
            '- Customer has entered: '.($entries ? "yes ({$entries->likes_count} likes so far)" : 'no'),
        ]);
    }

    private function entryPayments(): ?string
    {
        $purchases = PhotoSlotPurchase::where('user_id', $this->user->id)
            ->latest()
            ->limit(self::RECENT)
            ->get();

        return $this->section(
            'Recent payments for extra entries / extra photos',
            $purchases->map(fn (PhotoSlotPurchase $purchase) => sprintf(
                '%s: %s, KES %d, status %s%s',
                $this->date($purchase->created_at),
                str_replace('_', ' ', $purchase->type),
                $purchase->amount,
                $purchase->status,
                $purchase->paid_at ? ', confirmed '.$this->date($purchase->paid_at) : '',
            ))->all(),
        );
    }

    private function photosBought(): ?string
    {
        $purchases = PhotoPurchase::where('buyer_id', $this->user->id)
            ->latest()
            ->limit(self::RECENT)
            ->get();

        return $this->section(
            'Recent photos the customer bought',
            $purchases->map(fn (PhotoPurchase $purchase) => sprintf(
                '%s: KES %d, status %s',
                $this->date($purchase->created_at),
                $purchase->amount,
                $purchase->status,
            ))->all(),
        );
    }

    private function photosSold(): ?string
    {
        $sales = PhotoPurchase::paid()
            ->where('seller_id', $this->user->id)
            ->latest()
            ->limit(self::RECENT)
            ->get();

        return $this->section(
            "Recent sales of the customer's photos",
            $sales->map(fn (PhotoPurchase $sale) => sprintf(
                '%s: sold for KES %d, customer\'s share KES %d, %s',
                $this->date($sale->created_at),
                $sale->amount,
                $sale->seller_amount,
                $sale->payout_paid_at
                    ? 'share sent to M-Pesa '.$this->date($sale->payout_paid_at)
                    : 'share not sent yet',
            ))->all(),
        );
    }

    private function wins(): ?string
    {
        $wins = PhotoCompetitionWinner::where('user_id', $this->user->id)
            ->latest()
            ->limit(self::RECENT)
            ->get();

        return $this->section(
            'Photo challenge wins',
            $wins->map(fn (PhotoCompetitionWinner $win) => sprintf(
                '%s: position %d, prize KES %d, %s',
                $this->date($win->created_at),
                $win->position,
                $win->prize_amount,
                $win->prize_paid_at
                    ? 'paid to M-Pesa '.$this->date($win->prize_paid_at)
                    : 'not paid yet',
            ))->all(),
        );
    }

    private function referrals(): ?string
    {
        $made = Referral::where('referrer_id', $this->user->id)->count();

        if ($made === 0) {
            return null;
        }

        return "## Referrals\n- Friends who signed up with the customer's link: {$made}";
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function section(string $title, array $lines): ?string
    {
        if ($lines === []) {
            return null;
        }

        return "## {$title}\n".implode("\n", array_map(fn ($line) => "- {$line}", $lines));
    }

    private function date(CarbonInterface|string|null $date): string
    {
        if (blank($date)) {
            return 'unknown';
        }

        return Carbon::parse($date)->timezone('Africa/Nairobi')->format('D j M Y, g:ia');
    }
}
