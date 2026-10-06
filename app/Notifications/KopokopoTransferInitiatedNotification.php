<?php

namespace App\Notifications;

use App\Events\KopokopoTransferInitiated;
use App\Http\Services\Service;
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

	/**
	 * The destination phone, in the local "0..." format people recognize
	 * (the event carries Kopokopo's "254..." format), shown so the
	 * recipient can confirm the money actually landed on their own number.
	 */
	protected function bodyLine(): string
	{
		$phone = Service::toLocalPhoneNumber($this->event->phoneNumber);

		return "KES {$this->event->amount} has been sent to your Mpesa {$phone}.";
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->from('al@mail.black.co.ke', 'Alphaxard from Black Gallery')
			->subject('Payment sent! 💸')
			->greeting('Hello ' . $notifiable->name . ',')
			->line($this->bodyLine())
			->line($this->event->description ?? 'Thank you for being part of Black Gallery!');
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => '/',
			'from' => 'Admin',
			'message' => $this->bodyLine(),
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('Payment sent! 💸')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($this->bodyLine())
			->data(['url' => '/']);
	}
}
