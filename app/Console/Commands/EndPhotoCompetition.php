<?php

namespace App\Console\Commands;

use App\Events\PhotoCompetitionEnded;
use App\Models\PhotoCompetition;
use App\Notifications\PhotoCompetitionWonNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:end-photo-competition')]
#[Description('Ends the active photo competition and crowns the winner.')]
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

        $winner = $competition->photos()
            ->orderByDesc('likes_count')
            ->orderBy('created_at')
            ->first();

        $competition->update([
            'status' => PhotoCompetition::STATUS_ENDED,
            'winner_photo_id' => $winner?->id,
        ]);

        if ($winner) {
            $winner->user->notify(new PhotoCompetitionWonNotification($competition));
        }

        broadcast(new PhotoCompetitionEnded($competition));

        $this->components->info("Ended competition #{$competition->id}. Winner: " . ($winner?->id ?? 'none'));
    }
}
