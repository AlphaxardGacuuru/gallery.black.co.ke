<?php

use App\Jobs\DeleteStaleTemporaryUploadsJob;
use App\Models\PhotoCompetition;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune Telescope entries older than 30 days
Schedule::command('telescope:prune --hours=720')->daily();

// TODO: `websockets:clean` came from the old laravel-websockets package,
// which this project no longer uses (replaced by Reverb). Remove or swap
// in a Reverb-equivalent cleanup task, then re-enable.
// Schedule::command('websockets:clean')->weekly();

Schedule::job(new DeleteStaleTemporaryUploadsJob)
    // ->everyMinute();
    ->dailyAt("01:00");

// Weekly photo challenge: day/time are admin-configurable (see
// AdminPhotoCompetitionController::updateSchedule and
// PhotoCompetition::schedule()). Checked every minute via a `when()`
// closure — evaluated only while the scheduler is actually running —
// rather than read eagerly here, so a DB hiccup can't break every artisan
// command that loads this file.
Schedule::command('app:start-photo-competition')
    ->everyMinute()
    ->when(fn() => PhotoCompetition::matchesScheduledMoment('start'));

Schedule::command('app:end-photo-competition')
    ->everyMinute()
    ->when(fn() => PhotoCompetition::matchesScheduledMoment('end'));
