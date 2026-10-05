<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\AdminNewUserSignupNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminNewUserSignupNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_always_sends_web_push(): void
    {
        $admin = User::factory()->create();
        $newUser = User::factory()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $notification = new AdminNewUserSignupNotification($newUser, null);

        $this->assertSame([WebPushChannel::class], $notification->via($admin));

        $webPush = $notification->toWebPush($admin, $notification);
        $this->assertInstanceOf(WebPushMessage::class, $webPush);

        $body = $webPush->toArray()['body'];
        $this->assertStringContainsString('Jane', $body);
        $this->assertStringContainsString('jane@example.com', $body);
        $this->assertStringNotContainsString('Referred by', $body);
    }

    #[Test]
    public function it_mentions_the_referrer_when_present(): void
    {
        $admin = User::factory()->create();
        $newUser = User::factory()->create(['name' => 'Jane']);
        $referrer = User::factory()->create(['name' => 'Bob']);

        $notification = new AdminNewUserSignupNotification($newUser, $referrer);
        $webPush = $notification->toWebPush($admin, $notification);

        $this->assertStringContainsString('Referred by Bob', $webPush->toArray()['body']);
    }
}
