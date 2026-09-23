<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_challenge_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get(route('two-factor.login'));

        $response->assertRedirect(route('login'));
    }
}
