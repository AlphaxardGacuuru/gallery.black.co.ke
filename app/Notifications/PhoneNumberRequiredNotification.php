<?php

namespace App\Notifications;

use App\Notifications\Messages\WebPushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Sent on every channel when sign-up screening flags a new account, asking
 * for a phone number before they can like. Not opt-out: it's about their
 * account, like UserFlaggedNotification.
 */
class PhoneNumberRequiredNotification extends Notification implements ShouldQueue
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
		return 'Add your M-Pesa phone number in Settings > Profile to start liking photos. Each number can only be on one account, which keeps likes fair.';
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->subject('Add your phone number to start liking')
			->greeting('Hello ' . $notifiable->name . ',')
			->line('Before you can like photos on Black Gallery, please add your M-Pesa phone number.')
			->line('Each number can only be on one account, which keeps the challenge fair. It\'s also where your prizes are sent.')
			->action('Add my phone number', url(self::URL));
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
			->title('Add your phone number to start liking')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($this->bodyLine())
			->openAction('add-phone', 'Add phone number', self::URL)
			->data(['url' => self::URL]);
	}
}
