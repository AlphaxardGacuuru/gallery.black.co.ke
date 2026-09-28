<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\ReferralSignupNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReferralSignupNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_email_database_and_web_push_by_default(): void
    {
        $referrer = User::factory()->create();
        $newUser = User::factory()->create(['name' => 'Jane']);

        $notification = new ReferralSignupNotification($newUser);

        $this->assertSame(['mail', 'database', WebPushChannel::class], $notification->via($referrer));

        $mail = $notification->toMail($referrer);
        $this->assertInstanceOf(MailMessage::class, $mail);

        $array = $notification->toArray($referrer);
        $this->assertStringContainsString('Jane', $array['message']);
    }

    #[Test]
    public function it_does_not_send_mail_when_the_referrer_opted_out(): void
    {
        $referrer = User::factory()->create([
            'settings' => ['referralSignupNotification' => false],
        ]);
        $newUser = User::factory()->create(['name' => 'Jane']);

        $notification = new ReferralSignupNotification($newUser);

        $this->assertNotContains('mail', $notification->via($referrer));
        $this->assertContains('database', $notification->via($referrer));
        $this->assertContains(WebPushChannel::class, $notification->via($referrer));
    }
}
