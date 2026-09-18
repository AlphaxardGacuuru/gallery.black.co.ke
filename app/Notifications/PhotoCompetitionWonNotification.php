<?php

namespace App\Notifications;

use App\Models\PhotoCompetition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PhotoCompetitionWonNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public function __construct(protected PhotoCompetition $competition) {}

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
			->subject('You won this week\'s challenge! 🏆')
			->greeting('Congratulations ' . $notifiable->name . '!')
			->line("Your photo took first place in this week's challenge — KES {$this->competition->prize_amount} is on its way.")
			->action('View the challenge', url('/discover'))
			->line('Thank you for entering, and see you next week!');
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => '/discover',
			'from' => 'Admin',
			'message' => "Congratulations! Your photo won this week's challenge — KES {$this->competition->prize_amount} is on its way.",
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('You won this week\'s challenge! 🏆')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body("Your photo took first place — KES {$this->competition->prize_amount} is on its way.")
			->data(['url' => '/discover']);
	}
}
