<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LegacyRegisterRedirectTest extends TestCase
{
    public function test_old_referral_links_are_forwarded_to_login_with_the_ref(): void
    {
        $this->get('/register?ref=abc-123')->assertRedirect('/login?ref=abc-123');
    }

    public function test_register_without_query_string_is_forwarded_to_login(): void
    {
        $this->get('/register')->assertRedirect('/login');
    }
}
