<?php

namespace App\Notifications;

use App\Notifications\Messages\WebPushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * The user just got the verified badge.
 */
class UserVerifiedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public const URL = '/compete';

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		return ['database', WebPushChannel::class];
	}

	protected function bodyLine(): string
	{
		return 'You\'re now verified. The badge shows on your profile picture and your photos.';
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => self::URL,
			'from' => 'Admin',
			'message' => $this->bodyLine(),
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('You\'re verified! ✅')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($this->bodyLine())
			->openAction('submit', 'Submit a photo', '/photos/create')
			->data(['url' => self::URL]);
	}
}
