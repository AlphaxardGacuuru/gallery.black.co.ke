<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent on every channel when an admin flags the user, telling them how to
 * dispute it. Not opt-out: it's about their account, like a payment email.
 */
class UserFlaggedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public const URL = '/settings/profile';

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		return ['mail', 'database', WebPushChannel::class];
	}

	protected function bodyLine(): string
	{
		return 'Your account has been flagged for unusual activity. Your likes won\'t count and payouts are on hold. If this is a mistake, update your M-Pesa phone number in Settings > Profile and we\'ll review your account.';
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->subject('Your account has been flagged')
			->greeting('Hello ' . $notifiable->name . ',')
			->line('Your Black Gallery account has been flagged for unusual activity.')
			->line('While it\'s flagged, your likes won\'t count toward the challenge, and prizes and payouts are on hold.')
			->line('If you think this is a mistake, update your M-Pesa phone number in Settings > Profile. That tells us to review your account.')
			->action('Update my phone number', url(self::URL));
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
			->title('Your account has been flagged')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($this->bodyLine())
			->data(['url' => self::URL]);
	}
}
