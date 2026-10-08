<?php

namespace Tests\Feature\Auth;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Covers the real end-to-end referral-link flow: a ?ref=<referrerId> query
 * param survives Google's OAuth redirect round-trip via a short-lived
 * cookie (AuthenticatedSessionController::redirectToProvider), and gets
 * threaded into Referral::record() on a successful new-user callback
 * (AuthService::findOrCreateFromSocialite). The dedicated
 * GoogleAuthenticationTest file is stale (pre-dates this app's SPA/Sanctum
 * rewrite) and fails entirely for unrelated reasons, so this file is the
 * only current coverage of the referral-crediting path specifically.
 */
class GoogleSignupReferralTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogleUser(string $id, string $email): void
    {
        $provider = Mockery::mock();
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn($id);
        $googleUser->shouldReceive('getEmail')->andReturn($email);
        $googleUser->shouldReceive('getName')->andReturn('New Google User');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/a/avatar');

        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }

    public function test_the_redirect_step_stashes_the_ref_query_param_in_a_cookie(): void
    {
        $referrer = User::factory()->create();

        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get("/login/google/redirect?ref={$referrer->id}");

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
        $response->assertCookie('referral_ref', $referrer->id);
    }

    public function test_the_redirect_step_sets_no_cookie_without_a_ref_param(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $response = $this->get('/login/google/redirect');

        $response->assertCookieMissing('referral_ref');
    }

    public function test_a_new_signup_with_a_valid_referral_cookie_records_the_referral(): void
    {
        $referrer = User::factory()->create();
        $this->mockGoogleUser('google-new-1', 'new-signup-1@example.com');

        $this->withCookie('referral_ref', $referrer->id)
            ->get('/login/google/callback')
            ->assertRedirect();

        $this->assertAuthenticated();

        $newUser = User::where('email', 'new-signup-1@example.com')->firstOrFail();

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id,
            'referred_id' => $newUser->id,
        ]);
    }

    public function test_a_new_signup_with_no_referral_cookie_records_nothing(): void
    {
        $this->mockGoogleUser('google-new-2', 'new-signup-2@example.com');

        $this->get('/login/google/callback')->assertRedirect();

        $newUser = User::where('email', 'new-signup-2@example.com')->firstOrFail();

        $this->assertDatabaseMissing('referrals', ['referred_id' => $newUser->id]);
    }

    public function test_a_new_signup_with_a_nonexistent_referrer_id_records_nothing(): void
    {
        $this->mockGoogleUser('google-new-3', 'new-signup-3@example.com');

        $this->withCookie('referral_ref', (string) Str::uuid())
            ->get('/login/google/callback')
            ->assertRedirect();

        $newUser = User::where('email', 'new-signup-3@example.com')->firstOrFail();

        $this->assertDatabaseMissing('referrals', ['referred_id' => $newUser->id]);
    }

    public function test_an_existing_user_logging_in_does_not_get_a_new_referral(): void
    {
        $referrer = User::factory()->create();
        $existingUser = User::factory()->create([
            'google_id' => null,
            'email' => 'already-here@example.com',
        ]);
        $this->mockGoogleUser('google-existing-1', 'already-here@example.com');

        $this->withCookie('referral_ref', $referrer->id)
            ->get('/login/google/callback')
            ->assertRedirect();

        $this->assertAuthenticatedAs($existingUser->fresh());
        $this->assertDatabaseMissing('referrals', ['referred_id' => $existingUser->id]);
    }

    public function test_a_user_cannot_be_credited_as_their_own_referrer(): void
    {
        // Referral::record()'s self-referral guard is unreachable through
        // the Google signup path (a brand new user's id doesn't exist yet
        // to put in a link), so it's covered directly here instead.
        $user = User::factory()->create();

        Referral::record($user->id, $user);

        $this->assertDatabaseMissing('referrals', ['referred_id' => $user->id]);
    }
}
