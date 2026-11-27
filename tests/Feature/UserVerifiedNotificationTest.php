<?php

namespace Tests\Feature;

use App\Enums\VerificationReason;
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

    private function admin(): User
    {
        return User::firstWhere('email', config('admin.email'))
            ?? User::factory()->create(['email' => config('admin.email')]);
    }

    private function setVerified(User $user, bool $verified): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/verify", [
                'verified' => $verified,
                'reason' => $verified ? VerificationReason::KNOWN_PHOTOGRAPHER->value : null,
            ])
            ->assertOk();
    }

    public function test_admin_verifying_records_the_reason_and_who(): void
    {
        $user = User::factory()->create();

        $this->setVerified($user, true);

        $verification = $user->fresh()->verification;
        $this->assertTrue($verification['verified']);
        $this->assertSame(VerificationReason::KNOWN_PHOTOGRAPHER->value, $verification['reason']);
        $this->assertSame($this->admin()->id, $verification['by']);
        $this->assertNotNull($verification['at']);
    }

    public function test_admin_must_pick_a_reason_from_the_admin_choices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/verify", ['verified' => true])
            ->assertJsonValidationErrors('reason');

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/verify", [
                'verified' => true,
                'reason' => VerificationReason::NEW_SIGNUP->value,
            ])
            ->assertJsonValidationErrors('reason');

        $this->assertFalse($user->fresh()->verified);
    }

    public function test_revoking_records_who_removed_it(): void
    {
        $user = User::factory()->verifiedBadge()->create();

        $this->setVerified($user, false);

        $verification = $user->fresh()->verification;
        $this->assertFalse($verification['verified']);
        $this->assertSame($this->admin()->id, $verification['by']);
    }

    public function test_admin_verifying_a_user_notifies_them_once(): void
    {
        $user = User::factory()->create();

        $this->setVerified($user, true);
        $this->setVerified($user, true);

        Notification::assertSentToTimes($user, UserVerifiedNotification::class, 1);
    }

    public function test_revoking_the_badge_sends_nothing(): void
    {
        $user = User::factory()->verifiedBadge()->create();

        $this->setVerified($user, false);

        Notification::assertNotSentTo($user, UserVerifiedNotification::class);
    }

    public function test_winning_notifies_only_newly_verified_winners(): void
    {
        Setting::updateOrCreate(['key' => 'photo_prize_tiers'], ['value' => [500, 300]]);

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();

        $firstTimer = User::factory()->create();
        $alreadyVerified = User::factory()->verifiedBadge()->create();
        $loser = User::factory()->create();

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
        $this->assertSame(VerificationReason::COMPETITION_WINNER, $firstTimer->fresh()->verification_reason);
        $this->assertNull($firstTimer->fresh()->verification['by']);
    }

    public function test_the_notification_shows_in_app_and_as_a_push(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['database', WebPushChannel::class], (new UserVerifiedNotification)->via($user));
        $this->assertSame(UserVerifiedNotification::URL, (new UserVerifiedNotification)->toArray($user)['url']);
    }
}
