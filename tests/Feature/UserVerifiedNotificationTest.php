<?php

namespace Tests\Feature;

use App\Models\PhotoCompetition;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\UserVerifiedNotification;
use NotificationChannels\WebPush\WebPushChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserVerifiedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function setVerified(User $user, bool $verified): void
    {
        $admin = User::firstWhere('email', config('admin.email'))
            ?? User::factory()->create(['email' => config('admin.email')]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/verify", ['verified' => $verified])
            ->assertOk();
    }

    public function test_admin_verifying_a_user_notifies_them_once(): void
    {
        $user = User::factory()->create(['verified' => false]);

        $this->setVerified($user, true);
        $this->setVerified($user, true);

        Notification::assertSentToTimes($user, UserVerifiedNotification::class, 1);
    }

    public function test_revoking_the_badge_sends_nothing(): void
    {
        $user = User::factory()->create(['verified' => true]);

        $this->setVerified($user, false);

        Notification::assertNotSentTo($user, UserVerifiedNotification::class);
    }

    public function test_winning_notifies_only_newly_verified_winners(): void
    {
        Setting::updateOrCreate(['key' => 'photo_prize_tiers'], ['value' => [500, 300]]);

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();

        $firstTimer = User::factory()->create(['verified' => false]);
        $alreadyVerified = User::factory()->create(['verified' => true]);
        $loser = User::factory()->create(['verified' => false]);

        collect([[$firstTimer, 5], [$alreadyVerified, 3], [$loser, 0]])
            ->each(fn (array $entry) => $competition->photos()->create([
                'user_id' => $entry[0]->id,
                'disk' => 'public',
                'path' => "photos/{$entry[0]->id}.jpg",
                'likes_count' => $entry[1],
            ]));

        $this->artisan('app:end-photo-competition')->assertSuccessful();

        Notification::assertSentTo($firstTimer, UserVerifiedNotification::class);
        Notification::assertNotSentTo($alreadyVerified, UserVerifiedNotification::class);
        Notification::assertNotSentTo($loser, UserVerifiedNotification::class);
        $this->assertTrue($firstTimer->fresh()->verified);
    }

    public function test_the_notification_shows_in_app_and_as_a_push(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['database', WebPushChannel::class], (new UserVerifiedNotification)->via($user));
        $this->assertSame(UserVerifiedNotification::URL, (new UserVerifiedNotification)->toArray($user)['url']);
    }
}
