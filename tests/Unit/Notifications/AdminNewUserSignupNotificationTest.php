<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\AdminNewUserSignupNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminNewUserSignupNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_always_sends_mail_database_and_web_push(): void
    {
        $admin = User::factory()->create([
            'settings' => ['competitionWonNotification' => false],
        ]);
        $newUser = User::factory()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $notification = new AdminNewUserSignupNotification($newUser, null);

        $this->assertSame(['mail', 'database', WebPushChannel::class], $notification->via($admin));

        $mail = $notification->toMail($admin);
        $this->assertInstanceOf(MailMessage::class, $mail);

        $array = $notification->toArray($admin);
        $this->assertStringContainsString('Jane', $array['message']);
        $this->assertStringContainsString('jane@example.com', $array['message']);
        $this->assertStringNotContainsString('Referred by', $array['message']);
    }

    #[Test]
    public function it_mentions_the_referrer_when_present(): void
    {
        $admin = User::factory()->create();
        $newUser = User::factory()->create(['name' => 'Jane']);
        $referrer = User::factory()->create(['name' => 'Bob']);

        $notification = new AdminNewUserSignupNotification($newUser, $referrer);
        $array = $notification->toArray($admin);

        $this->assertStringContainsString('Referred by Bob', $array['message']);
    }
}
