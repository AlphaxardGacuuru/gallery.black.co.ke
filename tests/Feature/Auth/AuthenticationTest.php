<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest('web');
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logged Out']);
    }

    public function test_users_can_logout_with_a_bearer_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $response = $this->withToken($token)->post(route('logout'));

        $response->assertStatus(200);
        $this->assertCount(0, $user->tokens()->get());
    }
}
