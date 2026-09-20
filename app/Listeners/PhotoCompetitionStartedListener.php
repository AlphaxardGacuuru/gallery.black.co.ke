<?php

namespace App\Listeners;

use App\Events\PhotoCompetitionStarted;
use App\Models\User;
use App\Notifications\PhotoCompetitionStartedNotification;
use Illuminate\Support\Facades\Notification;

class PhotoCompetitionStartedListener
{
	public function handle(PhotoCompetitionStarted $event): void
	{
		User::query()
			->whereHas('pushSubscriptions')
			->chunkById(200, function ($users) use ($event) {
				Notification::send(
					$users,
					new PhotoCompetitionStartedNotification($event->competition),
				);
			});
	}
}
