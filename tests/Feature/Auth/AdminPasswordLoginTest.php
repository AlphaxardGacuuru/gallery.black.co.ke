<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminPasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    private function passwordAdmin(): User
    {
        return User::factory()->create([
            'email' => config('admin.password_login_email'),
            'password' => Hash::make('secret-password'),
        ]);
    }

    private function signedUrl(): string
    {
        return URL::temporarySignedRoute('admin-password-login', now()->addMinutes(10), absolute: false);
    }

    public function test_only_an_admin_can_get_the_signed_link(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/admin/password-login-link')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['email' => config('admin.email')]), 'sanctum')
            ->postJson('/api/admin/password-login-link')
            ->assertOk()
            ->assertJsonPath('data', fn (string $url) => str_starts_with($url, '/admin-login?expires='));
    }

    public function test_the_page_needs_a_valid_signature(): void
    {
        $this->get('/admin-login')
            ->assertForbidden();

        $this->get($this->signedUrl())
            ->assertOk();
    }

    public function test_the_password_admin_can_log_in_through_the_signed_link(): void
    {
        $admin = $this->passwordAdmin();

        $this->postJson($this->signedUrl(), ['email' => $admin->email, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_logging_in_without_the_signed_link_is_forbidden(): void
    {
        $admin = $this->passwordAdmin();

        $this->postJson('/admin-login', ['email' => $admin->email, 'password' => 'secret-password'])
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_no_other_account_can_log_in_with_a_password(): void
    {
        $this->passwordAdmin();
        $other = User::factory()->create([
            'email' => config('admin.email'),
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson($this->signedUrl(), ['email' => $other->email, 'password' => 'secret-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $admin = $this->passwordAdmin();

        $this->postJson($this->signedUrl(), ['email' => $admin->email, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_the_password_admin_can_open_the_admin_portal(): void
    {
        $this->actingAs($this->passwordAdmin(), 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk();
    }
}
