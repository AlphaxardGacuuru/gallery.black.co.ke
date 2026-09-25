<?php

namespace Tests\Unit\Notifications;

use App\Models\PhotoCompetition;
use App\Models\User;
use App\Notifications\PhotoCompetitionEndedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhotoCompetitionEndedNotificationTest extends TestCase
{
	use RefreshDatabase;

	#[Test]
	public function it_sends_email_database_and_web_push_channels(): void
	{
		$competition = PhotoCompetition::factory()->create();
		$user = User::factory()->create(['name' => 'Jane']);

		$notification = new PhotoCompetitionEndedNotification($competition);

		$this->assertSame(['mail', 'database', WebPushChannel::class], $notification->via($user));

		$mail = $notification->toMail($user);
		$this->assertInstanceOf(MailMessage::class, $mail);
		$this->assertSame('This week\'s challenge has ended', $mail->subject);

		$this->assertSame([
			'url' => '/discover',
			'from' => 'Admin',
			'message' => 'This week\'s photo challenge has closed and the results are in.',
		], $notification->toArray($user));
	}

	#[Test]
	public function it_does_not_send_mail_when_the_user_opted_out(): void
	{
		$competition = PhotoCompetition::factory()->create();
		$user = User::factory()->create([
			'settings' => ['competitionStartedNotification' => false],
		]);

		$notification = new PhotoCompetitionEndedNotification($competition);

		$this->assertNotContains('mail', $notification->via($user));
	}
}
