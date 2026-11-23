<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\SafaricomPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateUserRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_update_accepts_nullable_fields_and_preserves_existing_values(): void
    {
        $user = User::factory()->create([
            'name' => 'Existing Name',
            'email' => 'existing@example.com',
            'phone' => '0712345678',
        ]);

        // UpdateUserRequest now only backs the admin route.
        $admin = User::factory()->create(['email' => config('admin.email')]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/users/' . $user->id . '/verify', [
                'name' => null,
                'email' => null,
                'phone' => '0798765432',
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Existing Name',
            'email' => 'existing@example.com',
            'phone' => '0798765432',
        ]);
    }

    public function test_admin_can_change_a_locked_phone_number_to_another_safaricom_number(): void
    {
        $user = User::factory()->create(['phone' => '0711223344']);
        $admin = User::factory()->create(['email' => config('admin.email')]);

        $this->assertFalse($user->canChangePhone());

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/users/' . $user->id . '/verify', ['phone' => '+254 722 334 455'])
            ->assertOk();

        $this->assertSame('0722334455', $user->fresh()->phone);
    }

    public function test_admin_cannot_set_a_non_safaricom_or_taken_phone_number(): void
    {
        $user = User::factory()->create(['phone' => '0711223344']);
        User::factory()->create(['phone' => '0722334455']);
        $admin = User::factory()->create(['email' => config('admin.email')]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/users/' . $user->id . '/verify', ['phone' => '0733123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => SafaricomPhoneNumber::MESSAGE]);

        // Same number in another format still counts as taken.
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/users/' . $user->id . '/verify', ['phone' => '254722334455'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'Another account already uses this phone number.']);

        $this->assertSame('0711223344', $user->fresh()->phone);
    }
}
