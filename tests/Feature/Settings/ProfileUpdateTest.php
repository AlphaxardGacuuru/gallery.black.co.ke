<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Rules\SafaricomPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_a_user_without_a_phone_number_can_add_one()
    {
        $user = User::factory()->create(['phone' => null]);

        $this->actingAs($user)
            ->patchJson(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '0711223344'])
            ->assertOk();

        $this->assertSame('0711223344', $user->fresh()->phone);
    }

    public function test_a_non_safaricom_phone_number_is_rejected()
    {
        $user = User::factory()->create(['phone' => null]);

        // Airtel, Telkom, a made-up prefix and a short number.
        collect(['0733123456', '0771123456', '0101123456', '071122334'])
            ->each(fn ($phone) => $this->actingAs($user)
                ->patchJson(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => $phone])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['phone' => SafaricomPhoneNumber::MESSAGE]));

        $this->assertNull($user->fresh()->phone);
    }

    public function test_a_safaricom_phone_number_is_stored_in_the_local_format()
    {
        collect(['+254 711 223 344', '254711223344', '0711-223-344'])
            ->each(function ($phone) {
                $user = User::factory()->create(['phone' => null]);

                $this->actingAs($user)
                    ->patchJson(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => $phone])
                    ->assertOk();

                $this->assertSame('0711223344', $user->fresh()->phone);

                $user->delete();
            });

        $user = User::factory()->create(['phone' => null]);

        $this->actingAs($user)
            ->patchJson(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '+254110123456'])
            ->assertOk();

        $this->assertSame('0110123456', $user->fresh()->phone);
    }

    public function test_a_set_phone_number_can_be_changed_but_not_removed()
    {
        $user = User::factory()->create(['phone' => '0711223344']);

        $this->actingAs($user)
            ->patchJson(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        // Leaving it out, or sending it unchanged, doesn't flag anyone.
        $this->actingAs($user)
            ->patchJson(route('profile.update'), ['name' => 'New Name', 'email' => $user->email])
            ->assertOk();
        $this->actingAs($user)
            ->patchJson(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '0711223344'])
            ->assertOk();
        $this->assertFalse($user->fresh()->isFlagged());

        $this->actingAs($user)
            ->patchJson(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '0799887766'])
            ->assertOk();

        $this->assertSame('0799887766', $user->fresh()->phone);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
