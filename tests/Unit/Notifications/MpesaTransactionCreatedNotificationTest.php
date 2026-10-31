<?php

namespace Tests\Unit\Notifications;

use App\Models\MPESATransaction;
use App\Models\User;
use App\Notifications\MpesaTransactionCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MpesaTransactionCreatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_mail_database_broadcast_and_web_push(): void
    {
        $user = User::factory()->create(['name' => 'Jane']);
        $mpesaTransaction = MPESATransaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 500,
        ]);

        $notification = new MpesaTransactionCreatedNotification($mpesaTransaction);

        $this->assertSame(
            ['mail', 'database', 'broadcast', WebPushChannel::class],
            $notification->via($user)
        );

        $mail = $notification->toMail($user);
        $this->assertInstanceOf(MailMessage::class, $mail);

        // Sent from the app's own MAIL_FROM_ADDRESS, as Black Gallery
        $this->assertEmpty($mail->from);
        $this->assertSame(url('/'), $mail->actionUrl);
        $this->assertStringNotContainsString('Property', implode(' ', $mail->outroLines));
        $this->assertInstanceOf(BroadcastMessage::class, $notification->toBroadcast($user));

        $webPush = $notification->toWebPush($user, $notification);
        $this->assertInstanceOf(WebPushMessage::class, $webPush);
        $this->assertStringContainsString('KES 500', $webPush->toArray()['body']);

        $array = $notification->toArray($user);
        $this->assertStringContainsString('KES 500', $array['message']);
    }
}
