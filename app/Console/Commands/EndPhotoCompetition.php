<?php

namespace App\Console\Commands;

use App\Events\PhotoCompetitionEnded;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:end-photo-competition')]
#[Description('Ends the active photo competition and crowns its top-10 tiered winners.')]
class EndPhotoCompetition extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $competition = PhotoCompetition::active()->first();

        if (! $competition) {
            $this->components->warn('No active competition to end.');

            return;
        }

        // Seed photos compete and place exactly like real entries, so the
        // Unsplash photographers they promote can win any position.
        $topPhotos = $competition->photos()
            ->with('user:id,is_seed')
            ->orderByDesc('likes_count')
            ->orderBy('created_at')
            ->limit(10)
            ->get(['id', 'user_id']);

        $tiers = PhotoCompetition::prizeTiers();
        $now = now();

        $rows = $topPhotos
            ->values()
            ->map(fn($photo, $index) => [
                'id' => (string) Str::uuid(),
                'competition_id' => $competition->id,
                'photo_id' => $photo->id,
                'user_id' => $photo->user_id,
                'position' => $index + 1,
                'prize_amount' => $tiers[$index] ?? 0,
                // There's nobody real to pay for a seed placement, so it's
                // marked paid straight away (no Kopokopo transfer is made).
                // That keeps it out of the admin's unpaid payout queue.
                'prize_paid_at' => $photo->user?->is_seed ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            // Tiers are contiguous/descending by convention — the first
            // non-positive one means nothing further is paid either.
            ->takeWhile(fn(array $row) => $row['prize_amount'] > 0);

        if ($rows->isNotEmpty()) {
            PhotoCompetitionWinner::query()->insert($rows->all());
        }

        $competition->update(['status' => PhotoCompetition::STATUS_ENDED]);
        $competition->load(['winners.user', 'photos.user']);

        // PhotoCompetitionEndedListener notifies each winner — dispatching
        // still broadcasts too, since the event implements ShouldBroadcast.
        PhotoCompetitionEnded::dispatch($competition);

        $this->components->info("Ended competition #{$competition->id}. Winners: {$rows->count()}");
    }
}
