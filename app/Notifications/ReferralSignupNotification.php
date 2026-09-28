<?php

namespace App\Notifications;

use App\Enums\EmailNotificationCategory;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ReferralSignupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected User $newUser,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        $channels = ['database', WebPushChannel::class];

        if ($notifiable->wantsEmail(EmailNotificationCategory::REFERRAL_SIGNED_UP)) {
            array_unshift($channels, 'mail');
        }

        return $channels;
    }

    protected function bodyLine(): string
    {
        return "🎉 {$this->newUser->name} just joined Black Gallery using your referral link!";
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->from('al@mail.black.co.ke', 'Alphaxard from Black Gallery')
            ->subject('Your referral just joined Black Gallery!')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->bodyLine())
            ->action('View your referrals', url('/settings/referrals'))
            ->markdown('notifications::email', [
                'unsubscribeUrl' => $notifiable->unsubscribeUrlFor(EmailNotificationCategory::REFERRAL_SIGNED_UP),
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'url' => '/referrals',
            'from' => 'Admin',
            'message' => $this->bodyLine(),
        ];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Your referral just joined!')
            ->icon('/notification-badge-192x192.png')
            ->badge('/notification-badge-192x192.png')
            ->body($this->bodyLine())
            ->data(['url' => '/referrals']);
    }
}
