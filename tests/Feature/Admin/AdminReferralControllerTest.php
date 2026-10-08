<?php

namespace Tests\Feature\Admin;

use App\Events\KopokopoTransferInitiated;
use App\Http\Services\KopokopoTransferService;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReferralSignupNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    private function setThresholdAndReward(int $threshold, float $rewardAmount): void
    {
        Setting::query()->updateOrCreate(['key' => 'referral_threshold'], ['value' => $threshold]);
        Setting::query()->updateOrCreate(['key' => 'referral_reward_amount'], ['value' => $rewardAmount]);
    }

    public function test_paying_a_referrer_initiates_a_transfer_without_marking_referrals_paid_yet(): void
    {
        Event::fake([KopokopoTransferInitiated::class]);
        $this->setThresholdAndReward(threshold: 2, rewardAmount: 100);

        $referrer = User::factory()->create(['phone' => '0700123456']);
        $referrals = collect([
            Referral::create(['referrer_id' => $referrer->id, 'referred_id' => User::factory()->create()->id]),
            Referral::create(['referrer_id' => $referrer->id, 'referred_id' => User::factory()->create()->id]),
        ]);

        // Same partial-mock boundary as PhotoCompetitionWinnersPayoutTest:
        // the real Kopokopo/M-Pesa call happens inside initiateTransfer()
        // via a fresh K2 SDK client built inside the service, so only that
        // call is mocked, while payReferrer()'s own logic (guards, the
        // kopokopo_reference/amount_paid update) runs for real.
        $this->partialMock(KopokopoTransferService::class, function ($mock) {
            $mock->shouldReceive('initiateTransfer')
                ->once()
                ->withArgs(fn($request) => (int) $request->input('amount') === 100)
                ->andReturn([true, 'Transfer initiated, awaiting confirmation from Kopokopo', [
                    'status' => 'success',
                    'location' => 'https://api.kopokopo.com/api/v1/send_money/referrer-transfer-123',
                ]]);
        });

        $admin = User::factory()->create(['email' => config('admin.email')]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/referrals/{$referrer->id}/pay");

        $response->assertOk()->assertJsonPath('status', true);

        foreach ($referrals as $referral) {
            $referral->refresh();
            $this->assertNull($referral->paid_at);
            $this->assertSame('referrer-transfer-123', $referral->kopokopo_reference);
            $this->assertEquals(50.0, (float) $referral->amount_paid);
        }

        Event::assertNotDispatched(KopokopoTransferInitiated::class);
    }

    public function test_a_confirmed_webhook_marks_referrals_paid_and_notifies_the_referrer(): void
    {
        Event::fake([KopokopoTransferInitiated::class]);

        $referrer = User::factory()->create(['phone' => '0700123456']);
        $referrals = collect([
            Referral::create([
                'referrer_id' => $referrer->id,
                'referred_id' => User::factory()->create()->id,
                'kopokopo_reference' => 'referrer-transfer-123',
                'amount_paid' => 50,
            ]),
            Referral::create([
                'referrer_id' => $referrer->id,
                'referred_id' => User::factory()->create()->id,
                'kopokopo_reference' => 'referrer-transfer-123',
                'amount_paid' => 50,
            ]),
        ]);

        $this->postJson('/api/kopokopo-transfers', [
            'data' => [
                'id' => 'referrer-transfer-123',
                'attributes' => [
                    'status' => 'Processed',
                    'created_at' => now()->toIso8601String(),
                    'currency' => 'KES',
                    'destinations' => [['amount' => 100]],
                    'transfer_batches' => [['status' => 'Transferred']],
                    'metadata' => [],
                ],
            ],
        ])->assertOk();

        foreach ($referrals as $referral) {
            $this->assertNotNull($referral->fresh()->paid_at);
        }

        Event::assertDispatched(
            KopokopoTransferInitiated::class,
            fn(KopokopoTransferInitiated $event): bool => $event->amount === 100.0,
        );
    }

    public function test_a_failed_webhook_clears_the_referral_payout_so_it_can_be_retried(): void
    {
        $referrer = User::factory()->create(['phone' => '0700123456']);
        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => User::factory()->create()->id,
            'kopokopo_reference' => 'referrer-transfer-456',
            'amount_paid' => 50,
        ]);

        $this->postJson('/api/kopokopo-transfers', [
            'data' => [
                'id' => 'referrer-transfer-456',
                'attributes' => [
                    'status' => 'Failed',
                    'created_at' => now()->toIso8601String(),
                    'currency' => 'KES',
                    'destinations' => [['amount' => 50]],
                    'metadata' => [],
                ],
            ],
        ])->assertOk();

        $referral->refresh();
        $this->assertNull($referral->paid_at);
        $this->assertNull($referral->kopokopo_reference);
        $this->assertNull($referral->amount_paid);
    }
}
