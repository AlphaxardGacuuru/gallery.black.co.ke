<?php

namespace App\Notifications;

use App\Enums\EmailNotificationCategory;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent once, halfway through a challenge. Users who haven't entered are
 * nudged to submit a photo; users who have are nudged to share their
 * referral link and pull in more likes.
 */
class PhotoCompetitionReminderNotification extends Notification implements ShouldQueue
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

	/**
	 * The notifiable's most-liked entry in this challenge, if they entered.
	 */
	protected function entryFor($notifiable): ?Photo
	{
		return $this->competition->photos()
			->where('user_id', $notifiable->getKey())
			->orderByDesc('likes_count')
			->first();
	}

	protected function deadline(): string
	{
		return $this->competition->ends_at->format('l g:ia');
	}

	protected function referralUrl($notifiable): string
	{
		return url('/login?ref=' . $notifiable->getKey());
	}

	protected function likesPhrase(Photo $entry): string
	{
		return $entry->likes_count === 1 ? '1 like' : "{$entry->likes_count} likes";
	}

	protected function topPrizeLine(): ?string
	{
		return PhotoCompetition::prizeTierLines()[0] ?? null;
	}

	public function toMail($notifiable): MailMessage
	{
		$entry = $this->entryFor($notifiable);

		$message = (new MailMessage)
			->from('al@mail.black.co.ke', 'Alphaxard from Black Gallery')
			->greeting('Hello ' . $notifiable->name . ',');

		if ($entry) {
			$message
				->subject('You\'re halfway there. Time to rally some likes')
				->line("This week's challenge is halfway through and your photo has {$this->likesPhrase($entry)} so far.")
				->line("Voting closes {$this->deadline()}. The more people who see your photo, the more likes it gets, so share your referral link with friends and family:")
				->line("[{$this->referralUrl($notifiable)}]({$this->referralUrl($notifiable)})")
				->lines(PhotoCompetition::prizeTierLines())
				->action('See how you\'re doing', url('/'));
		} else {
			$message
				->subject('There\'s still time to enter this week\'s challenge')
				->line("This week's photo challenge is halfway through and you haven't entered yet. You still have until {$this->deadline()} to submit your best shot.")
				->line('Here\'s what\'s up for grabs:')
				->lines(PhotoCompetition::prizeTierLines())
				->action('Enter the challenge', url('/'))
				->line('Once you\'re in, share your referral link so friends can find your photo and like it.');
		}

		return $message->markdown('notifications::email', [
			'unsubscribeUrl' => $notifiable->unsubscribeUrlFor(EmailNotificationCategory::COMPETITION_STARTED),
		]);
	}

	/**
	 * @return array{title: string, body: string, url: string}
	 */
	protected function shortMessage($notifiable): array
	{
		$entry = $this->entryFor($notifiable);

		if ($entry) {
			return [
				'title' => 'Halfway there. Rally some likes',
				'body' => "Your photo has {$this->likesPhrase($entry)}. Share your referral link to get more before {$this->deadline()}.",
				'url' => '/settings/referrals',
			];
		}

		$prize = $this->topPrizeLine();

		return [
			'title' => 'Still time to enter this week\'s challenge',
			'body' => "Submit your best shot before {$this->deadline()}" . ($prize ? " ({$prize})." : '.'),
			'url' => '/',
		];
	}

	public function toArray($notifiable): array
	{
		$message = $this->shortMessage($notifiable);

		return [
			'url' => $message['url'],
			'from' => 'Admin',
			'message' => $message['body'],
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		$message = $this->shortMessage($notifiable);

		return (new WebPushMessage)
			->title($message['title'])
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($message['body'])
			->data(['url' => $message['url']]);
	}
}
