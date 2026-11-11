<?php

namespace App\Notifications;

use App\Models\SupportTicketMessage;
use App\Notifications\Messages\WebPushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * An operational alert to the admin account: the customer wrote again on a
 * ticket a person is handling. Always sent, like the escalation alert.
 */
class AdminSupportTicketMessageNotification extends Notification implements ShouldQueue
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
        return "/admin/support?ticket={$this->message->support_ticket_id}";
    }

    protected function bodyLine(): string
    {
        $name = $this->message->ticket?->user?->name ?? 'A user';

        return "{$name}: ".Str::limit($this->message->body, 120);
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
            ->title("New message on {$this->message->ticket?->number}")
            ->icon('/notification-badge-192x192.png')
            ->badge('/notification-badge-192x192.png')
            ->body($this->bodyLine())
            ->openAction('reply', 'Reply', $this->url())
            ->signedAction('resolve', 'Resolve', 'push-actions.support-tickets.resolve', [
                'user' => $notifiable->id,
                'supportTicket' => $this->message->support_ticket_id,
            ])
            ->data(['url' => $this->url()]);
    }
}
