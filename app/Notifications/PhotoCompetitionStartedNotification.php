<?php

namespace App\Notifications;

use App\Models\PhotoCompetition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PhotoCompetitionStartedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public function __construct(protected PhotoCompetition $competition) {}

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		return [WebPushChannel::class];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('This week\'s photo challenge is live')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body("Submit your best shot before Friday 8pm to win KES {$this->competition->prize_amount}.")
			->data(['url' => '/']);
	}
}
