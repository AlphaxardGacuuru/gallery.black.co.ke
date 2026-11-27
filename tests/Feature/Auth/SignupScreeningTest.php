<?php

namespace Tests\Feature\Auth;

use App\Ai\Agents\SignupScreeningAgent;
use App\Jobs\ScreenNewSignupJob;
use App\Models\AiUsage;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserLogin;
use App\Notifications\PhoneNumberRequiredNotification;
use App\Notifications\UserFlaggedNotification;
use App\Services\SignupScreeningService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SignupScreeningTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '197.248.10.20';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function signUpWithGoogle(): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google-new-user');
        $googleUser->shouldReceive('getEmail')->andReturn('new-user@example.com');
        $googleUser->shouldReceive('getName')->andReturn('New User');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/a/avatar');

        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])
            ->get(route('login.provider.callback', ['website' => 'google']));
    }

    private function screen(User $user): void
    {
        app(SignupScreeningService::class)->screen($user, self::IP);
    }

    private function screeningFlagged(): User
    {
        return tap(
            User::factory()->create(['phone' => null]),
            fn(User $user) => $user
                ->forceFill(['flag' => ['at' => now(), 'reason' => 'Looks like a duplicate', 'source' => User::FLAG_SOURCE_SIGNUP_SCREENING]])
                ->save()
        );
    }

    private function addPhone(User $user): void
    {
        $this->actingAs($user)
            ->patchJson(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '0711223344'])
            ->assertSessionHasNoErrors();
    }

    public function test_new_google_sign_ups_are_queued_for_screening_and_not_flagged_yet(): void
    {
        Queue::fake();

        $this->signUpWithGoogle();

        $user = User::query()
            ->where('email', 'new-user@example.com')
            ->firstOrFail();
        $this->assertFalse($user->isFlagged());
        Queue::assertPushed(ScreenNewSignupJob::class, fn(ScreenNewSignupJob $job) => $job->user->is($user) && $job->ipAddress === self::IP);
    }

    public function test_returning_users_are_not_screened(): void
    {
        Queue::fake();
        User::factory()->create(['email' => 'new-user@example.com']);

        $this->signUpWithGoogle();

        Queue::assertNotPushed(ScreenNewSignupJob::class);
    }

    public function test_an_account_the_agent_flags_is_asked_for_a_phone_number(): void
    {
        SignupScreeningAgent::fake([['flag' => true, 'reason' => 'Looks like a second account of jkamau@gmail.com.']]);
        $user = User::factory()->create(['phone' => null]);

        $this->screen($user);

        $user->refresh();
        $this->assertTrue($user->isFlaggedUntilPhone());
        $this->assertSame('Looks like a second account of jkamau@gmail.com.', $user->flag_reason);
        $this->assertDatabaseHas('ai_usages', ['user_id' => $user->id, 'feature' => AiUsage::FEATURE_SIGNUP_SCREENING]);
        Notification::assertSentTo($user, PhoneNumberRequiredNotification::class);
        Notification::assertNotSentTo($user, UserFlaggedNotification::class);
    }

    public function test_an_account_the_agent_clears_is_left_alone(): void
    {
        SignupScreeningAgent::fake([['flag' => false, 'reason' => 'Ordinary email.']]);
        $user = User::factory()->create(['phone' => null]);

        $this->screen($user);

        $this->assertFalse($user->fresh()->isFlagged());
        Notification::assertNothingSent();
    }

    public function test_nothing_is_screened_when_switched_off(): void
    {
        SignupScreeningAgent::fake();
        Setting::query()
            ->create(['key' => User::SETTING_SCREEN_NEW_SIGNUPS, 'value' => false]);

        $this->screen(User::factory()->create(['phone' => null]));

        SignupScreeningAgent::assertNeverPrompted();
    }

    public function test_nothing_is_screened_while_ai_agents_are_off(): void
    {
        SignupScreeningAgent::fake();
        Setting::query()
            ->create(['key' => AiUsage::SETTING_AGENTS_ENABLED, 'value' => false]);

        $this->screen(User::factory()->create(['phone' => null]));

        SignupScreeningAgent::assertNeverPrompted();
    }

    public function test_an_agent_failure_leaves_the_user_alone(): void
    {
        SignupScreeningAgent::fake(fn() => throw new RuntimeException('Gemini is down'));
        $user = User::factory()->create(['phone' => null]);

        $this->screen($user);

        $this->assertFalse($user->fresh()->isFlagged());
    }

    public function test_the_agent_is_shown_look_alike_accounts(): void
    {
        SignupScreeningAgent::fake([['flag' => false, 'reason' => 'Ordinary email.']]);
        User::factory()->create(['email' => 'j.kamau7@gmail.com']);
        $sameNetwork = User::factory()->create(['email' => 'someone.else@gmail.com']);
        UserLogin::factory()->create(['user_id' => $sameNetwork->id, 'ip_address' => self::IP]);
        User::factory()->create(['email' => 'unrelated@gmail.com']);
        $user = User::factory()->create(['email' => 'jkamau@gmail.com', 'phone' => null]);

        $this->screen($user);

        SignupScreeningAgent::assertPrompted(fn(AgentPrompt $prompt) => $prompt->contains('j.kamau7@gmail.com')
            && $prompt->contains('someone.else@gmail.com | Joined')
            && ! $prompt->contains('unrelated@gmail.com'));
    }

    public function test_look_alikes_note_a_shared_network(): void
    {
        $sameNetwork = User::factory()->create(['email' => 'jkamau2@gmail.com']);
        UserLogin::factory()->create(['user_id' => $sameNetwork->id, 'ip_address' => self::IP]);
        $otherNetwork = User::factory()->create(['email' => 'jkamau3@gmail.com']);
        $user = User::factory()->create(['email' => 'jkamau@gmail.com']);

        $lookAlikes = app(SignupScreeningService::class)
            ->lookAlikes($user, self::IP)
            ->keyBy('id');

        $this->assertCount(2, $lookAlikes);
        $this->assertTrue((bool) $lookAlikes[$sameNetwork->id]->shares_ip);
        $this->assertFalse((bool) $lookAlikes[$otherNetwork->id]->shares_ip);
    }

    public function test_adding_a_phone_number_lifts_a_screening_flag(): void
    {
        $user = $this->screeningFlagged();

        $this->addPhone($user);

        $user->refresh();
        $this->assertFalse($user->isFlagged());
        $this->assertNull($user->flag_source);
        $this->assertNull($user->flag_refuted_at);
    }

    public function test_adding_a_phone_number_only_disputes_an_admin_flag(): void
    {
        $user = tap(
            User::factory()->create(['phone' => null]),
            fn(User $user) => $user
                ->forceFill(['flag' => ['at' => now(), 'reason' => 'Like ring', 'source' => User::FLAG_SOURCE_ADMIN]])
                ->save()
        );

        $this->addPhone($user);

        $user->refresh();
        $this->assertTrue($user->isFlagged());
        $this->assertNotNull($user->flag_refuted_at);
    }

    public function test_a_screening_flagged_user_is_asked_for_a_phone_number_when_liking(): void
    {
        $photo = Photo::factory()->create([
            'competition_id' => PhotoCompetition::factory()->create()->id,
        ]);

        $this->actingAs($this->screeningFlagged(), 'sanctum')
            ->postJson("/api/photos/{$photo->id}/like")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id' => 'Add your M-Pesa phone number in Settings > Profile to start liking photos.']);
    }

    public function test_admins_can_switch_screening_off(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users/settings')
            ->assertOk()
            ->assertJsonPath('data.screenNewSignups', true);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/users/settings', ['screenNewSignups' => false])
            ->assertOk();

        $this->assertFalse(User::screensNewSignups());
    }

    public function test_non_admins_cannot_change_the_setting(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/admin/users/settings', ['screenNewSignups' => false])
            ->assertForbidden();
    }
}
