<?php

namespace Tests\Unit\Notifications;

use App\Models\Photo;
use App\Models\User;
use App\Notifications\PhotoLikedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhotoLikedNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_email_database_and_web_push_by_default(): void
    {
        $owner = User::factory()->create(['name' => 'Jane']);
        $liker = User::factory()->create(['name' => 'Bob']);
        $photo = Photo::factory()->create(['user_id' => $owner->id]);

        $notification = new PhotoLikedNotification($photo, $liker);

        $this->assertSame(['mail', 'database', WebPushChannel::class], $notification->via($owner));

        $mail = $notification->toMail($owner);
        $this->assertInstanceOf(MailMessage::class, $mail);

        $array = $notification->toArray($owner);
        $this->assertStringContainsString('Bob', $array['message']);
        $this->assertSame('/photos/' . $photo->id, $array['url']);

        $webPush = $notification->toWebPush($owner, $notification);
        $this->assertStringContainsString('Bob', $webPush->toArray()['body']);
    }

    #[Test]
    public function it_does_not_send_mail_when_the_owner_opted_out(): void
    {
        $owner = User::factory()->create(['settings' => ['photoLikedNotification' => false]]);
        $liker = User::factory()->create();
        $photo = Photo::factory()->create(['user_id' => $owner->id]);

        $notification = new PhotoLikedNotification($photo, $liker);

        $this->assertNotContains('mail', $notification->via($owner));
        $this->assertContains('database', $notification->via($owner));
        $this->assertContains(WebPushChannel::class, $notification->via($owner));
    }
}
