<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\PhoneNumberRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminRequestPhoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => config('admin.email')]);
    }

    public function test_admin_can_ask_a_user_without_a_phone_to_add_one(): void
    {
        $user = User::factory()->create(['phone' => null]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/request-phone")
            ->assertOk();

        Notification::assertSentTo(
            $user,
            PhoneNumberRequestedNotification::class,
            fn ($notification, array $channels) => in_array('mail', $channels)
        );
    }

    public function test_each_request_is_counted_for_the_users_table(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['phone' => null]);

        collect(range(1, 2))->each(fn () => $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/request-phone")
            ->assertOk());

        $this->assertSame(2, $user->fresh()->phone_requests_count);
        $this->assertNotNull($user->fresh()->phone_requested_at);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?search=' . urlencode($user->email))
            ->assertOk()
            ->assertJsonPath('data.0.phoneRequestsCount', 2);
    }

    public function test_a_user_who_already_has_a_phone_is_not_asked(): void
    {
        $user = User::factory()->create(['phone' => '0712345678']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/request-phone")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        Notification::assertNotSentTo($user, PhoneNumberRequestedNotification::class);
    }

    public function test_seed_accounts_are_not_emailed(): void
    {
        $user = User::factory()->create(['phone' => null, 'is_seed' => true]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/request-phone")
            ->assertOk();

        Notification::assertNotSentTo($user, PhoneNumberRequestedNotification::class);
        $this->assertSame(0, $user->fresh()->phone_requests_count);
    }

    public function test_non_admins_cannot_send_requests(): void
    {
        $user = User::factory()->create(['phone' => null]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/request-phone")
            ->assertForbidden();

        Notification::assertNothingSent();
    }
}
