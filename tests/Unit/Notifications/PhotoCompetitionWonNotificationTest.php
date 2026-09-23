<?php

namespace Tests\Unit\Notifications;

use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\User;
use App\Notifications\PhotoCompetitionWonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhotoCompetitionWonNotificationTest extends TestCase
{
	use RefreshDatabase;

	#[Test]
	public function it_sends_email_database_and_web_push_channels_for_first_place(): void
	{
		$competition = PhotoCompetition::factory()->create();
		$user = User::factory()->create(['name' => 'Jane']);
		$winner = PhotoCompetitionWinner::factory()->make([
			'position' => 1,
			'prize_amount' => 500,
		]);

		$notification = new PhotoCompetitionWonNotification($competition, $winner);

		$this->assertSame(['mail', 'database', WebPushChannel::class], $notification->via($user));

		$mail = $notification->toMail($user);
		$this->assertInstanceOf(MailMessage::class, $mail);
		$this->assertStringContainsString('won', $mail->subject);
		$this->assertStringContainsString('🏆', $mail->subject);

		$array = $notification->toArray($user);
		$this->assertStringContainsString('first place', $array['message']);
		$this->assertStringContainsString('KES 500', $array['message']);
	}

	#[Test]
	public function it_uses_position_aware_copy_for_lower_ranks(): void
	{
		$competition = PhotoCompetition::factory()->create();
		$user = User::factory()->create(['name' => 'Jane']);
		$winner = PhotoCompetitionWinner::factory()->make([
			'position' => 3,
			'prize_amount' => 100,
		]);

		$notification = new PhotoCompetitionWonNotification($competition, $winner);
		$mail = $notification->toMail($user);

		$this->assertStringContainsString('#3', $mail->subject);
		$this->assertStringNotContainsString('won', $mail->subject);

		$array = $notification->toArray($user);
		$this->assertStringContainsString('#3', $array['message']);
		$this->assertStringContainsString('KES 100', $array['message']);
		$this->assertStringNotContainsString('first place', $array['message']);
	}

	#[Test]
	public function it_does_not_send_mail_when_the_user_opted_out(): void
	{
		$competition = PhotoCompetition::factory()->create();
		$user = User::factory()->create([
			'settings' => ['competitionWonNotification' => false],
		]);
		$winner = PhotoCompetitionWinner::factory()->make(['position' => 1]);

		$notification = new PhotoCompetitionWonNotification($competition, $winner);

		$this->assertNotContains('mail', $notification->via($user));
	}
}
