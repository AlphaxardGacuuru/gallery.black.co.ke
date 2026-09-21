<?php

namespace Tests\Unit\Notifications;

use App\Models\PhotoCompetition;
use App\Models\User;
use App\Notifications\PhotoCompetitionStartedNotification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhotoCompetitionStartedNotificationTest extends TestCase
{
	#[Test]
	public function it_sends_email_database_and_web_push_channels(): void
	{
		$competition = PhotoCompetition::factory()->create();
		$user = User::factory()->create(['name' => 'Jane']);

		$notification = new PhotoCompetitionStartedNotification($competition);

		$this->assertSame(['mail', 'database', WebPushChannel::class], $notification->via($user));

		$mail = $notification->toMail($user);
		$this->assertInstanceOf(MailMessage::class, $mail);
		$this->assertSame('This week\'s photo challenge is live', $mail->subject);

		$this->assertSame([
			'url' => '/',
			'from' => 'Admin',
			'message' => "Submit your best shot before {$competition->ends_at->format('l g:ia')} to win KES {$competition->prize_amount}.",
		], $notification->toArray($user));
	}
}
