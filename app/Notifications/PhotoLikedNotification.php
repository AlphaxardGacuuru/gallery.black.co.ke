<?php

namespace App\Notifications;

use App\Enums\EmailNotificationCategory;
use App\Models\Photo;
use App\Models\User;
use App\Notifications\Messages\WebPushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

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

	/**
	 * The liker's photo in the same running challenge, for "Like back",
	 * unless $notifiable already liked it or is flagged and can't like.
	 */
	protected function likeBackPhoto(User $notifiable): ?Photo
	{
		if ($notifiable->isFlagged()) {
			return null;
		}

		return Photo::query()
			->where('user_id', $this->likedBy->id)
			->where('competition_id', $this->photo->competition_id)
			->whereHas('competition', fn ($query) => $query->active())
			->whereDoesntHave('likes', fn ($query) => $query->where('user_id', $notifiable->id))
			->latest()
			->first();
	}

	protected function bodyLine(): string
	{
		return "{$this->likedBy->name} liked your photo!";
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->subject('Someone liked your photo! ❤️')
			->greeting('Hello ' . $notifiable->name . ',')
			->line($this->bodyLine())
			->action('View your photo', url('/photos/' . $this->photo->id . '/show'))
			->markdown('notifications::email', [
				'unsubscribeUrl' => $notifiable->unsubscribeUrlFor(EmailNotificationCategory::PHOTO_LIKED),
			]);
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => '/photos/' . $this->photo->id . '/show',
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
			->when(
				$this->likeBackPhoto($notifiable),
				fn ($message, Photo $photo) => $message->signedAction('like-back', 'Like back', 'push-actions.photos.like', [
					'user' => $notifiable->id,
					'photo' => $photo->id,
				])
			)
			->openAction('view', 'View photo', '/photos/' . $this->photo->id . '/show')
			->data(['url' => '/photos/' . $this->photo->id . '/show']);
	}
}
