<?php

namespace App\Notifications;

use App\Events\KopokopoTransferInitiated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class KopokopoTransferInitiatedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public function __construct(protected KopokopoTransferInitiated $event) {}

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		return ['mail', 'database', WebPushChannel::class];
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->from('al@mail.black.co.ke', 'Alphaxard from Black Gallery')
			->subject('Payment sent! 💸')
			->greeting('Hello ' . $notifiable->name . ',')
			->line("KES {$this->event->amount} has been sent to your M-Pesa.")
			->line($this->event->description ?? 'Thank you for being part of Black Gallery!');
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => '/',
			'from' => 'Admin',
			'message' => "KES {$this->event->amount} has been sent to your M-Pesa.",
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('Payment sent! 💸')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body("KES {$this->event->amount} has been sent to your M-Pesa.")
			->data(['url' => '/']);
	}
}
