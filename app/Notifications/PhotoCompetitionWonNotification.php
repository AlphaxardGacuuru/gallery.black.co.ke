<?php

namespace App\Notifications;

use App\Enums\EmailNotificationCategory;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PhotoCompetitionWonNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public function __construct(
		protected PhotoCompetition $competition,
		protected PhotoCompetitionWinner $winner,
	) {}

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		$channels = ['database', WebPushChannel::class];

		if ($notifiable->wantsEmail(EmailNotificationCategory::COMPETITION_WON)) {
			array_unshift($channels, 'mail');
		}

		return $channels;
	}

	protected function isFirstPlace(): bool
	{
		return $this->winner->position === 1;
	}

	protected function subject(): string
	{
		return $this->isFirstPlace()
			? 'You won this week\'s challenge! 🏆'
			: "You placed #{$this->winner->position} this week! 🎉";
	}

	protected function bodyLine(): string
	{
		return $this->isFirstPlace()
			? "Your photo took first place in this week's challenge — KES {$this->winner->prize_amount} is on its way."
			: "Your photo placed #{$this->winner->position} in this week's challenge — KES {$this->winner->prize_amount} is on its way.";
	}

	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->from('al@mail.black.co.ke', 'Alphaxard from Black Gallery')
			->subject($this->subject())
			->greeting(($this->isFirstPlace() ? 'Congratulations ' : 'Nice shot, ') . $notifiable->name . '!')
			->line($this->bodyLine())
			->action('View the challenge', url('/discover'))
			->line('Thank you for entering, and see you next week!')
			->markdown('notifications::email', [
				'unsubscribeUrl' => $notifiable->unsubscribeUrlFor(EmailNotificationCategory::COMPETITION_WON),
			]);
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => '/discover',
			'from' => 'Admin',
			'message' => $this->bodyLine(),
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title($this->subject())
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($this->bodyLine())
			->data(['url' => '/discover']);
	}
}
