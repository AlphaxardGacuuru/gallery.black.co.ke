<?php

namespace Tests\Feature\Admin;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
