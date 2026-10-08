<?php

namespace Tests\Feature\Admin;

use App\Models\Referral;
use App\Models\User;
use App\Notifications\ReferralSignupNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminReferralControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_leaderboard_includes_each_referrers_phone_number(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $referrer = User::factory()->create(['phone' => '0700123456']);
        Referral::create(['referrer_id' => $referrer->id, 'referred_id' => User::factory()->create()->id]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/referrals');

        $response->assertOk()
            ->assertJsonPath('data.leaderboard.0.userId', $referrer->id)
            ->assertJsonPath('data.leaderboard.0.phone', '0700123456');
    }

    public function test_admin_can_attach_a_referrer_to_a_user_who_signed_up_without_one(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $referrer = User::factory()->create();
        $referred = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$referred->id}/referrer", ['referrerId' => $referrer->id])
            ->assertCreated()
            ->assertJsonPath('data.referrerName', $referrer->name);

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
        ]);

        Notification::assertSentTo($referrer, ReferralSignupNotification::class);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment(['referredBy' => ['id' => $referrer->id, 'name' => $referrer->name]]);
    }

    public function test_a_user_who_already_has_a_referrer_cannot_get_another(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $referred = User::factory()->create();
        $originalReferrer = User::factory()->create();
        Referral::create(['referrer_id' => $originalReferrer->id, 'referred_id' => $referred->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$referred->id}/referrer", ['referrerId' => User::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referrerId');

        $this->assertDatabaseCount('referrals', 1);
        Notification::assertNothingSent();
    }

    public function test_a_user_cannot_be_attached_as_their_own_referrer(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $user = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/referrer", ['referrerId' => $user->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('referrerId');

        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_non_admins_cannot_attach_a_referrer(): void
    {
        $referred = User::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admin/users/{$referred->id}/referrer", ['referrerId' => User::factory()->create()->id])
            ->assertForbidden();

        $this->assertDatabaseCount('referrals', 0);
    }
}
