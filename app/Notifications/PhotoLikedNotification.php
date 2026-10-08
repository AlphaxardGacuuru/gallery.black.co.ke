<?php

namespace App\Notifications;

use App\Enums\EmailNotificationCategory;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PhotoLikedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public function __construct(
		protected Photo $photo,
		protected User $likedBy,
	) {}

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		$channels = ['database', WebPushChannel::class];

		if ($notifiable->wantsEmail(EmailNotificationCategory::PHOTO_LIKED)) {
			array_unshift($channels, 'mail');
		}

		return $channels;
	}

	protected function bodyLine(): string
	{
		return "{$this->likedBy->name} liked your photo!";
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->from('al@mail.black.co.ke', 'Alphaxard from Black Gallery')
			->subject('Someone liked your photo! ❤️')
			->greeting('Hello ' . $notifiable->name . ',')
			->line($this->bodyLine())
			->action('View your photo', url('/photos/' . $this->photo->id))
			->markdown('notifications::email', [
				'unsubscribeUrl' => $notifiable->unsubscribeUrlFor(EmailNotificationCategory::PHOTO_LIKED),
			]);
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => '/photos/' . $this->photo->id,
			'from' => 'Admin',
			'message' => $this->bodyLine(),
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('New like! ❤️')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($this->bodyLine())
			->data(['url' => '/photos/' . $this->photo->id]);
	}
}
