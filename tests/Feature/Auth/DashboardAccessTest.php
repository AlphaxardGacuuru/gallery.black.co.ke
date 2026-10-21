<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Telescope and Horizon authorize against the web session, through the
 * viewTelescope / viewHorizon gates and the config('admin.dashboard_emails')
 * allowlist. Telescope's routes are disabled under phpunit, so the gates
 * are exercised directly.
 */
class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowlisted_users_can_view_both_dashboards(): void
    {
        config(['admin.dashboard_emails' => ['ops@example.com']]);
        $user = User::factory()->create(['email' => 'ops@example.com']);

        $this->assertTrue(Gate::forUser($user)->allows('viewTelescope'));
        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    }

    public function test_other_users_and_guests_cannot_view_the_dashboards(): void
    {
        config(['admin.dashboard_emails' => ['ops@example.com']]);
        $user = User::factory()->create(['email' => 'someone@example.com']);

        $this->assertFalse(Gate::forUser($user)->allows('viewTelescope'));
        $this->assertFalse(Gate::forUser($user)->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser(null)->allows('viewTelescope'));
        $this->assertFalse(Gate::forUser(null)->allows('viewHorizon'));
    }

    // Auth::login() rotates the session id itself (SessionGuard::
    // updateSession), so a session planted before sign-in can't carry over
    // into the one Telescope and Horizon authorize against.
    public function test_google_login_starts_a_fresh_session(): void
    {
        $provider = Mockery::mock();
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google-ops-1');
        $googleUser->shouldReceive('getEmail')->andReturn('ops@example.com');
        $googleUser->shouldReceive('getName')->andReturn('Ops User');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/a/avatar');
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->withSession(['planted' => true]);
        $sessionIdBeforeLogin = session()->getId();

        $this->get('/login/google/callback')->assertRedirect();

        $this->assertAuthenticated();
        $this->assertNotSame($sessionIdBeforeLogin, session()->getId());
    }
}
