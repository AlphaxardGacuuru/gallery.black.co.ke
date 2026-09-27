<?php

namespace Tests\Unit\Notifications;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WelcomeNotificationTest extends TestCase
{
	use RefreshDatabase;

	#[Test]
	public function it_lists_all_ten_current_prize_tiers(): void
	{
		Setting::query()->updateOrCreate(
			['key' => 'photo_prize_tiers'],
			['value' => [1000, 900, 800, 700, 600, 500, 400, 300, 200, 100]]
		);

		$mail = (new WelcomeNotification)->toMail(User::factory()->create());

		$this->assertContains('1st place: KES 1000', $mail->introLines);
		$this->assertContains('10th place: KES 100', $mail->introLines);
		$this->assertCount(10, array_filter($mail->introLines, fn(string $line) => str_contains($line, ' place: KES ')));
	}

	#[Test]
	public function it_stops_at_the_first_unpaid_tier(): void
	{
		Setting::query()->updateOrCreate(
			['key' => 'photo_prize_tiers'],
			['value' => [500, 300, 0, 100]]
		);

		$mail = (new WelcomeNotification)->toMail(User::factory()->create());

		$this->assertContains('2nd place: KES 300', $mail->introLines);
		$this->assertNotContains('4th place: KES 100', $mail->introLines);
	}
}
