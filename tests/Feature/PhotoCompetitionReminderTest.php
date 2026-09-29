<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\User;
use App\Notifications\PhotoCompetitionReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PhotoCompetitionReminderTest extends TestCase
{
    use RefreshDatabase;

    private function competition(): PhotoCompetition
    {
        return PhotoCompetition::factory()->create([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(2),
        ]);
    }

    public function test_reminder_is_not_sent_before_the_midpoint(): void
    {
        Notification::fake();
        User::factory()->create();
        $competition = PhotoCompetition::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(3),
        ]);

        $this->artisan('app:send-photo-competition-reminder')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($competition->fresh()->reminder_sent_at);
    }

    public function test_reminder_is_sent_to_every_user_once_past_the_midpoint(): void
    {
        Notification::fake();
        $users = User::factory()->count(2)->create();
        $competition = $this->competition();

        $this->artisan('app:send-photo-competition-reminder')->assertSuccessful();
        $this->artisan('app:send-photo-competition-reminder')->assertSuccessful();

        Notification::assertSentToTimes($users[0], PhotoCompetitionReminderNotification::class, 1);
        Notification::assertSentToTimes($users[1], PhotoCompetitionReminderNotification::class, 1);
        $this->assertNotNull($competition->fresh()->reminder_sent_at);
    }

    public function test_reminder_is_not_sent_for_an_ended_competition(): void
    {
        Notification::fake();
        User::factory()->create();
        PhotoCompetition::factory()->ended()->create([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(2),
        ]);

        $this->artisan('app:send-photo-competition-reminder')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_non_entrants_are_asked_to_enter(): void
    {
        $user = User::factory()->create();
        $notification = new PhotoCompetitionReminderNotification($this->competition());

        $this->assertStringContainsString('still time to enter', $notification->toMail($user)->subject);
        $this->assertSame('/', $notification->toArray($user)['url']);
    }

    public function test_entrants_are_asked_to_share_their_referral_link(): void
    {
        $user = User::factory()->create();
        $competition = $this->competition();
        Photo::factory()->for($competition, 'competition')->create([
            'user_id' => $user->id,
            'likes_count' => 3,
        ]);
        $notification = new PhotoCompetitionReminderNotification($competition);

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('rally some likes', $mail->subject);
        $this->assertStringContainsString('/login?ref=' . $user->id, implode(' ', $mail->introLines));
        $this->assertStringContainsString('3 likes', $notification->toArray($user)['message']);
    }
}
