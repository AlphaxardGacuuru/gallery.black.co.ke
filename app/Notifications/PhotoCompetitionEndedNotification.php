<?php

namespace App\Notifications;

use App\Enums\EmailNotificationCategory;
use App\Models\PhotoCompetition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PhotoCompetitionEndedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public function __construct(protected PhotoCompetition $competition) {}

	/**
	 * @return array<int, string>
	 */
	public function via(mixed $notifiable): array
	{
		$channels = ['database', WebPushChannel::class];

		if ($notifiable->wantsEmail(EmailNotificationCategory::COMPETITION_STARTED)) {
			array_unshift($channels, 'mail');
		}

		return $channels;
	}

	public function toMail(mixed $notifiable): MailMessage
	{
		return (new MailMessage)
			->from('al@mail.black.co.ke', 'Alphaxard from Black Gallery')
			->subject('This week\'s challenge has ended')
			->greeting('Hello ' . $notifiable->name . ',')
			->line('This week\'s photo challenge has closed and the results are in.')
			->action('See the results', url('/discover'))
			->line('Thanks for entering — a new challenge opens soon!')
			->markdown('notifications::email', [
				'unsubscribeUrl' => $notifiable->unsubscribeUrlFor(EmailNotificationCategory::COMPETITION_STARTED),
			]);
	}

	public function toArray(mixed $notifiable): array
	{
		return [
			'url' => '/discover',
			'from' => 'Admin',
			'message' => 'This week\'s photo challenge has closed and the results are in.',
		];
	}

	public function toWebPush(mixed $notifiable, mixed $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('This week\'s challenge has ended')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body('This week\'s photo challenge has closed — see who won.')
			->data(['url' => '/discover']);
	}
}
