<?php

namespace App\Console\Commands;

use App\Models\PhotoCompetition;
use App\Models\User;
use App\Notifications\PhotoCompetitionReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('app:send-photo-competition-reminder')]
#[Description('Sends the mid-challenge reminder once the active competition passes its halfway point.')]
class SendPhotoCompetitionReminder extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $competition = PhotoCompetition::active()
            ->whereNull('reminder_sent_at')
            ->where('ends_at', '>', now())
            ->first();

        if (! $competition || now()->lessThan($competition->midpoint())) {
            return;
        }

        // Claim the reminder atomically so overlapping scheduler runs can
        // never send it twice.
        $claimed = PhotoCompetition::query()
            ->whereKey($competition->getKey())
            ->whereNull('reminder_sent_at')
            ->update(['reminder_sent_at' => now()]);

        if (! $claimed) {
            return;
        }

        User::query()->chunkById(200, function ($users) use ($competition) {
            Notification::send(
                $users,
                new PhotoCompetitionReminderNotification($competition),
            );
        });

        $this->components->info("Sent the mid-challenge reminder for competition #{$competition->id}.");
    }
}
