<?php

namespace App\Console\Commands;

use App\Models\PhotoCompetition;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PhotoCompetitionStartedNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

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

        $prizeAmount = (int) (Setting::query()
            ->where('key', 'photo_prize_amount')
            ->value('value') ?? 500);

        $competition = PhotoCompetition::create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => PhotoCompetition::STATUS_ACTIVE,
            'prize_amount' => $prizeAmount,
        ]);

        User::query()
            ->whereHas('pushSubscriptions')
            ->chunkById(200, function ($users) use ($competition) {
                Notification::send($users, new PhotoCompetitionStartedNotification($competition));
            });

        $this->components->info("Started competition #{$competition->id}, ends {$endsAt}.");
    }
}
