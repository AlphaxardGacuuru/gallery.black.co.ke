<?php

namespace Tests\Feature\Admin;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoLike;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\UserFlaggedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class AdminUserFlagTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = User::factory()->create(['email' => config('admin.email')]);
    }

    private function flag(User $user, bool $flagged = true): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/users/{$user->id}/flag", ['flagged' => $flagged, 'reason' => 'Like ring'])
            ->assertOk();
    }

    /**
     * A photo in a running challenge liked by $likers, with likes_count
     * set the way the app would have it.
     */
    private function likedPhoto(User ...$likers): Photo
    {
        $photo = Photo::factory()->create([
            'competition_id' => PhotoCompetition::factory()->create()->id,
            'likes_count' => count($likers),
        ]);

        collect($likers)->each(fn(User $liker) => PhotoLike::create(['photo_id' => $photo->id, 'user_id' => $liker->id]));

        return $photo;
    }

    public function test_flagging_notifies_the_user_on_every_channel(): void
    {
        $user = User::factory()->create();

        $this->flag($user);

        $this->assertNotNull($user->fresh()->flagged_at);
        $this->assertSame('Like ring', $user->fresh()->flag_reason);
        Notification::assertSentTo(
            $user,
            UserFlaggedNotification::class,
            fn($notification, array $channels) => $channels === ['mail', 'database', WebPushChannel::class]
        );
    }

    public function test_unflagging_sends_nothing(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['flagged_at' => now()])->save();

        $this->flag($user, false);

        $this->assertNull($user->fresh()->flagged_at);
        Notification::assertNothingSent();
    }

    public function test_seed_accounts_are_not_notified(): void
    {
        $seed = User::factory()->create();
        $seed->forceFill(['is_seed' => true])->save();

        $this->flag($seed);

        Notification::assertNotSentTo($seed, UserFlaggedNotification::class);
    }

    public function test_non_admins_cannot_flag(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson("/api/admin/users/{$user->id}/flag", ['flagged' => true])
            ->assertForbidden();
    }

    public function test_flagged_likes_stop_counting_until_unflagged(): void
    {
        $faker = User::factory()->create();
        $photo = $this->likedPhoto($faker, User::factory()->create());

        $this->flag($faker);
        $this->assertSame(1, $photo->fresh()->likes_count);
        $this->assertSame(2, $photo->likes()->count());

        $this->flag($faker, false);
        $this->assertSame(2, $photo->fresh()->likes_count);
    }

    public function test_ended_challenges_keep_their_counts(): void
    {
        $faker = User::factory()->create();
        $photo = $this->likedPhoto($faker);
        $photo->competition->update(['status' => PhotoCompetition::STATUS_ENDED]);

        $this->flag($faker);

        $this->assertSame(1, $photo->fresh()->likes_count);
    }

    public function test_a_flagged_user_cannot_like(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['flagged_at' => now()])->save();
        $photo = $this->likedPhoto();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/photos/{$photo->id}/like")
            ->assertUnprocessable();

        $this->assertSame(0, $photo->likes()->count());
    }

    public function test_updating_the_phone_number_refutes_the_flag_for_review(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $user->forceFill(['flagged_at' => now()])->save();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/settings/profile', ['name' => $user->name, 'email' => $user->email, 'phone' => '0711223344'])
            ->assertOk();

        $this->assertNotNull($user->fresh()->flag_refuted_at);
        $this->assertNotNull($user->fresh()->flagged_at);
    }

    public function test_the_reason_is_only_shown_to_the_admin(): void
    {
        $user = User::factory()->create();
        $this->flag($user);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/auth')
            ->assertOk()
            ->assertJsonPath('data.flaggedAt', fn($value) => $value !== null)
            ->assertJsonMissingPath('data.flagReason');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/users?flagged=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.flagReason', 'Like ring');
    }

    public function test_flagged_users_cannot_place_in_a_challenge(): void
    {
        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::active()->first();
        $cheat = User::factory()->create();
        $cheat->forceFill(['flagged_at' => now()])->save();
        $honest = User::factory()->create();

        collect([[$cheat, 50], [$honest, 3]])
            ->each(fn(array $entry) => Photo::factory()->create([
                'competition_id' => $competition->id,
                'user_id' => $entry[0]->id,
                'likes_count' => $entry[1],
            ]));

        $this->artisan('app:end-photo-competition')->assertSuccessful();

        $this->assertSame([$honest->id], $competition->winners()->pluck('user_id')->all());
    }

    public function test_flagged_users_are_not_paid_and_their_referrals_do_not_count(): void
    {
        Setting::query()->updateOrCreate(['key' => 'referral_threshold'], ['value' => 1]);
        $referrer = User::factory()->create();
        $fake = User::factory()->create();
        Referral::query()->create(['referrer_id' => $referrer->id, 'referred_id' => $fake->id, 'reward_rate' => 10]);

        $this->assertSame(10.0, Referral::eligiblePayout($referrer->id, 1)['totalAmount']);

        $this->flag($fake);
        $this->assertSame(0.0, Referral::eligiblePayout($referrer->id, 1)['totalAmount']);

        $this->flag($fake, false);
        $this->flag($referrer);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/referrals/{$referrer->id}/pay")
            ->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', "{$referrer->name} is flagged. Unflag them before paying.");
    }

    public function test_manage_likes_shows_and_removes_flagged_likes(): void
    {
        $faker = User::factory()->create();
        $honest = User::factory()->create();
        $photo = $this->likedPhoto($faker, $honest);
        $this->flag($faker);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/photos/{$photo->id}/likes?flagged=1&liked=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $faker->id)
            ->assertJsonPath('data.0.isFlagged', true);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/photos/{$photo->id}/likes/flagged")
            ->assertOk()
            ->assertJsonPath('data.removed', 1)
            ->assertJsonPath('data.likesCount', 1);

        $this->assertFalse($photo->likes()->where('user_id', $faker->id)->exists());
    }

    public function test_an_admin_like_for_a_flagged_user_does_not_count(): void
    {
        $faker = User::factory()->create();
        $faker->forceFill(['flagged_at' => now()])->save();
        $photo = $this->likedPhoto();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/photos/{$photo->id}/likes/{$faker->id}")
            ->assertOk()
            ->assertJsonPath('data.likesCount', 0);
    }
}
