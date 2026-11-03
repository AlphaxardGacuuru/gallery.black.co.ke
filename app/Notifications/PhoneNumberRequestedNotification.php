<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin asks a user to add their M-Pesa phone number, which
 * prizes and payouts are sent to.
 */
class PhoneNumberRequestedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public const URL = '/settings/profile';

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		return ['mail', 'database'];
	}

	protected function bodyLine(): string
	{
		return 'Add your M-Pesa phone number in Settings > Profile so we can send you prizes and payouts.';
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->subject('Add your M-Pesa number to get paid 📱')
			->greeting('Hello ' . $notifiable->name . ',')
			->line($this->bodyLine())
			->action('Add my phone number', url(self::URL))
			->line('It only takes a minute, and you won\'t miss a payment.');
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => self::URL,
			'from' => 'Admin',
			'message' => $this->bodyLine(),
		];
	}
}
