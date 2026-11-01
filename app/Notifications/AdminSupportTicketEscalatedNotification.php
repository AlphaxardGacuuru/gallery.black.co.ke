<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * An operational alert to the admin account: a support ticket needs a
 * person. Always sent, like AdminNewUserSignupNotification.
 */
class AdminSupportTicketEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected SupportTicket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    protected function bodyLine(): string
    {
        $name = $this->ticket->user?->name ?? 'A user';

        return "{$name} needs help with \"{$this->ticket->subject}\" ({$this->ticket->number}).";
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Support ticket needs you')
            ->icon('/notification-badge-192x192.png')
            ->badge('/notification-badge-192x192.png')
            ->body($this->bodyLine())
            ->data(['url' => "/admin/support?ticket={$this->ticket->id}"]);
    }
}
