<?php

namespace Tests\Unit\Notifications;

use App\Models\PhotoCompetition;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PhotoCompetitionStartedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhotoCompetitionStartedNotificationTest extends TestCase
{
	use RefreshDatabase;

	#[Test]
	public function it_sends_email_database_and_web_push_channels(): void
	{
		Setting::query()->updateOrCreate(
			['key' => 'photo_prize_tiers'],
			['value' => [500]]
		);

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
			'message' => "Submit your best shot before {$competition->ends_at->format('l g:ia')} — prizes: 1st place: KES 500.",
		], $notification->toArray($user));
	}

	#[Test]
	public function it_lists_every_paid_tier(): void
	{
		Setting::query()->updateOrCreate(
			['key' => 'photo_prize_tiers'],
			['value' => [500, 300, 100]]
		);

		$competition = PhotoCompetition::factory()->create();
		$user = User::factory()->create(['name' => 'Jane']);

		$notification = new PhotoCompetitionStartedNotification($competition);

		$array = $notification->toArray($user);
		$this->assertStringContainsString('1st place: KES 500', $array['message']);
		$this->assertStringContainsString('2nd place: KES 300', $array['message']);
		$this->assertStringContainsString('3rd place: KES 100', $array['message']);

		$mail = $notification->toMail($user)->render();
		$this->assertStringContainsString('1st place: KES 500', $mail);
		$this->assertStringContainsString('2nd place: KES 300', $mail);
		$this->assertStringContainsString('3rd place: KES 100', $mail);
	}
}
