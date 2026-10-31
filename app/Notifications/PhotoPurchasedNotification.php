<?php

namespace App\Notifications;

use App\Models\PhotoPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The buyer's receipt. Always emailed (there's no opt-out category for it),
 * since it confirms money they just paid.
 */
class PhotoPurchasedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public function __construct(protected PhotoPurchase $purchase) {}

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		return ['mail', 'database', WebPushChannel::class];
	}

	protected function url(): string
	{
		return "/photos/{$this->purchase->photo_id}/show";
	}

	protected function bodyLine(): string
	{
		$photographer = $this->purchase->seller?->name ?? 'the photographer';

		return "Payment of KES {$this->purchase->amount} received. Your full-resolution copy of {$photographer}'s photo is ready to download.";
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->subject('Your photo is ready to download')
			->greeting('Thanks ' . $notifiable->name . '!')
			->line($this->bodyLine())
			->action('Download your photo', url($this->url()))
			->line('You can download it again from the same page any time.')
			->markdown('notifications::email');
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => $this->url(),
			'from' => 'Admin',
			'message' => $this->bodyLine(),
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('Your photo is ready to download')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($this->bodyLine())
			->data(['url' => $this->url()]);
	}
}
