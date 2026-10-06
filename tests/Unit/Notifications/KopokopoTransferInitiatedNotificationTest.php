<?php

namespace Tests\Unit\Notifications;

use App\Events\KopokopoTransferInitiated;
use App\Models\User;
use App\Notifications\KopokopoTransferInitiatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KopokopoTransferInitiatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_the_destination_phone_in_local_format_on_every_channel(): void
    {
        $user = User::factory()->create(['name' => 'Jane']);
        $event = new KopokopoTransferInitiated('254700123456', 1000.0, 'Jane', 'A gift');

        $notification = new KopokopoTransferInitiatedNotification($event);

        $this->assertSame(['mail', 'database', WebPushChannel::class], $notification->via($user));

        $mail = $notification->toMail($user);
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertStringContainsString('0700123456', $mail->introLines[0]);
        $this->assertStringContainsString('KES 1000', $mail->introLines[0]);

        $array = $notification->toArray($user);
        $this->assertSame('KES 1000 has been sent to your Mpesa 0700123456.', $array['message']);

        $webPush = $notification->toWebPush($user, $notification);
        $this->assertInstanceOf(WebPushMessage::class, $webPush);
        $this->assertSame(
            'KES 1000 has been sent to your Mpesa 0700123456.',
            $webPush->toArray()['body']
        );
    }
}
