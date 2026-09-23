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

	private const ORDINALS = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];

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

	/**
	 * One line per paid position, e.g. "1st place: KES 500" — stops at the
	 * first unpaid tier, same convention EndPhotoCompetition ranks against.
	 *
	 * @return array<int, string>
	 */
	protected function tierLines(): array
	{
		return collect(PhotoCompetition::prizeTiers())
			->takeWhile(fn(int $amount) => $amount > 0)
			->map(fn(int $amount, int $index) => self::ORDINALS[$index] . ' place: KES ' . $amount)
			->all();
	}

	protected function tierSummary(): string
	{
		return implode(', ', $this->tierLines());
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->from('al@mail.black.co.ke', 'Alphaxard from Black Gallery')
			->subject('This week\'s photo challenge is live')
			->greeting('Hello ' . $notifiable->name . ',')
			->line("Submit your best shot before {$this->competition->ends_at->format('l g:ia')} — here's what's up for grabs this week:")
			->lines($this->tierLines())
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
			'message' => "Submit your best shot before {$this->competition->ends_at->format('l g:ia')} — prizes: {$this->tierSummary()}.",
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('This week\'s photo challenge is live')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body(
				"Submit your best shot before {$this->competition->ends_at->format('l g:ia')} — prizes: {$this->tierSummary()}."
			)
			->data(['url' => '/']);
	}
}
