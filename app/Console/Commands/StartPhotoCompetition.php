<?php

namespace App\Console\Commands;

use App\Events\PhotoCompetitionStarted;
use App\Models\PhotoCompetition;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:start-photo-competition')]
#[Description('Starts this week\'s photo competition using the configured schedule.')]
class StartPhotoCompetition extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        [$startsAt, $endsAt] = PhotoCompetition::scheduledWindowFor(now());

        if (PhotoCompetition::query()->where('starts_at', $startsAt)->exists()) {
            $this->components->warn('This week\'s competition already exists.');

            return;
        }

        // Defensive: an earlier competition should already be ended by
        // EndPhotoCompetition, but never leave two competitions active.
        PhotoCompetition::active()->update(['status' => PhotoCompetition::STATUS_ENDED]);

        $competition = PhotoCompetition::create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => PhotoCompetition::STATUS_ACTIVE,
        ]);

        PhotoCompetitionStarted::dispatch($competition);

        $this->components->info("Started competition #{$competition->id}, ends {$endsAt}.");
    }
}
