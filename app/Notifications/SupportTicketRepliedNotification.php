<?php

namespace App\Notifications;

use App\Models\SupportTicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Someone on the support team replied to the customer's ticket.
 */
class SupportTicketRepliedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	public function __construct(protected SupportTicketMessage $message) {}

	/**
	 * @return array<int, string>
	 */
	public function via($notifiable): array
	{
		return ['database', WebPushChannel::class];
	}

	protected function url(): string
	{
		return "/support?ticket={$this->message->support_ticket_id}";
	}

	protected function bodyLine(): string
	{
		return 'Support replied: ' . Str::limit($this->message->body, 120);
	}

	public function toArray($notifiable): array
	{
		return [
			'url' => $this->url(),
			'from' => 'Support',
			'message' => $this->bodyLine(),
		];
	}

	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title('New reply from support')
			->icon('/notification-badge-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($this->bodyLine())
			->data(['url' => $this->url()]);
	}
}
