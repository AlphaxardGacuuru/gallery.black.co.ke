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

class PhotoCompetitionStartedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public function __construct(protected PhotoCompetition $competition) {}

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		$channels = ['database', WebPushChannel::class];

		if ($notifiable->wantsEmail(EmailNotificationCategory::COMPETITION_STARTED)) {
			array_unshift($channels, 'mail');
		}

		return $channels;
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->from('al@mail.black.co.ke', 'Alphaxard from Black Gallery')
			->subject('This week\'s photo challenge is live')
			->greeting('Hello ' . $notifiable->name . ',')
			->line("Submit your best shot before {$this->competition->ends_at->format('l g:ia')} to win KES {$this->competition->prize_amount}.")
			->action('View the challenge', url('/'))
			->line('We\'ll be picking the winners soon — good luck!')
			->markdown('notifications::email', [
				'unsubscribeUrl' => $notifiable->unsubscribeUrlFor(EmailNotificationCategory::COMPETITION_STARTED),
			]);
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => '/',
			'from' => 'Admin',
			'message' => "Submit your best shot before {$this->competition->ends_at->format('l g:ia')} to win KES {$this->competition->prize_amount}.",
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('This week\'s photo challenge is live')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body(
				"Submit your best shot before {$this->competition->ends_at->format('l g:ia')} to win KES {$this->competition->prize_amount}."
			)
			->data(['url' => '/']);
	}
}
