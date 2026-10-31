<?php

namespace App\Notifications;

use App\Enums\EmailNotificationCategory;
use App\Models\PhotoPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PhotoSoldNotification extends Notification implements ShouldQueue
{
	use Queueable;

	/**
	 * @param  bool  $payoutStarted  Whether the photographer's share was
	 *                               sent to Kopokopo when the sale landed.
	 */
	public function __construct(
		protected PhotoPurchase $purchase,
		protected bool $payoutStarted,
	) {}

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		$channels = ['database', WebPushChannel::class];

		if ($notifiable->wantsEmail(EmailNotificationCategory::PHOTO_SOLD)) {
			array_unshift($channels, 'mail');
		}

		return $channels;
	}

	protected function url(): string
	{
		return "/photos/{$this->purchase->photo_id}/show";
	}

	protected function bodyLine(): string
	{
		$sold = "Someone bought your photo for KES {$this->purchase->amount}.";

		if ($this->purchase->seller_amount < 1) {
			return $sold;
		}

		if ($this->payoutStarted) {
			return "{$sold} Your share of KES {$this->purchase->seller_amount} is on its way to your M-Pesa.";
		}

		if (! $this->purchase->seller?->phone) {
			return "{$sold} Add your M-Pesa phone number in your profile so we can send your share of KES {$this->purchase->seller_amount}.";
		}

		return "{$sold} Your share is KES {$this->purchase->seller_amount}. We'll send it to your M-Pesa shortly.";
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->subject('You sold a photo! 💸')
			->greeting('Nice work, ' . $notifiable->name . '!')
			->line($this->bodyLine())
			->action('View your photo', url($this->url()))
			->markdown('notifications::email', [
				'unsubscribeUrl' => $notifiable->unsubscribeUrlFor(EmailNotificationCategory::PHOTO_SOLD),
			]);
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
			->title('You sold a photo! 💸')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($this->bodyLine())
			->data(['url' => $this->url()]);
	}
}
