<?php

namespace App\Notifications;

use App\Models\MpesaTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class MpesaTransactionCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected MpesaTransaction $mpesaTransaction;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(MpesaTransaction $mpesaTransaction)
    {
        $this->mpesaTransaction = $mpesaTransaction;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database', 'broadcast', WebPushChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->from("al@property.black.co.ke", "Alphaxard from Black Property")
            ->subject('Payment Received')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your Payment of KES '.number_format($this->mpesaTransaction->amount).' has been Received!')
            ->action('View', url('/#/admin/billing'))
            ->line('Thank you for choosing Black Property!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            "url" => "/admin/billing",
            "from" => "",
            "message" => "Payment of KES ".number_format($this->mpesaTransaction->amount)." received.",
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast(object $notifiable)
    {
        return new BroadcastMessage([
            "url" => "/admin/billing",
            "from" => "",
            "message" => "Payment of KES ".number_format($this->mpesaTransaction->amount)." received.",
        ]);
    }

    /**
     * Get the web push representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Payment Received')
            ->icon('/notification-badge-192x192.png')
            ->badge('/notification-badge-192x192.png')
            ->body('Payment of KES '.number_format($this->mpesaTransaction->amount).' received.')
            ->data(['url' => '/admin/billing']);
    }
}
