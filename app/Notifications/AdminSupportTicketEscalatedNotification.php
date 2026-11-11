<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Notifications\Messages\WebPushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

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
        return ['mail', 'database', WebPushChannel::class];
    }

    protected function url(): string
    {
        return "/admin/support?ticket={$this->ticket->id}";
    }

    protected function bodyLine(): string
    {
        $name = $this->ticket->user?->name ?? 'A user';

        return "{$name} needs help with \"{$this->ticket->subject}\" ({$this->ticket->number}).";
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Support ticket {$this->ticket->number} needs you")
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->bodyLine())
            ->line('The AI assistant has stopped replying, so the customer is waiting for a person.')
            ->action('Open the ticket', url($this->url()));
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
            ->title('Support ticket needs you')
            ->icon('/notification-badge-192x192.png')
            ->badge('/notification-badge-192x192.png')
            ->body($this->bodyLine())
            ->openAction('reply', 'Reply', $this->url())
            ->data(['url' => $this->url()]);
    }
}
