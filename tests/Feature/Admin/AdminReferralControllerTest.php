<?php

namespace Tests\Feature\Admin;

use App\Events\KopokopoTransferInitiated;
use App\Http\Services\KopokopoTransferService;
use App\Models\Photo;
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
                    'transfer_batches' => [[
                        'disbursements' => [['status' => 'Failed']],
                    ]],
                ],
            ],
        ])->assertOk();

        $referral->refresh();
        $this->assertNull($referral->paid_at);
        $this->assertNull($referral->kopokopo_reference);
        $this->assertNull($referral->amount_paid);
    }

    public function test_admin_can_set_the_photo_bonus_next_to_the_referral_reward(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/referrals/settings', [
                'threshold' => 5,
                'rewardAmount' => 50,
                'photoBonusAmount' => 20,
            ])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/referrals')
            ->assertOk()
            ->assertJsonPath('data.photoBonusAmount', 20);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/referrals/settings')
            ->assertOk()
            ->assertJsonPath('data.photoBonusAmount', 20);
    }

    public function test_a_referral_that_posts_a_photo_earns_a_bonus_even_below_the_threshold(): void
    {
        Event::fake([KopokopoTransferInitiated::class]);
        $this->setThresholdAndReward(threshold: 5, rewardAmount: 100);
        Setting::query()->updateOrCreate(['key' => 'referral_photo_bonus_amount'], ['value' => 20]);

        $referrer = User::factory()->create(['phone' => '0700123456']);
        $poster = User::factory()->create();
        $lurker = User::factory()->create();
        Photo::factory()->count(2)->create(['user_id' => $poster->id]);
        $postedReferral = Referral::create(['referrer_id' => $referrer->id, 'referred_id' => $poster->id]);
        $lurkerReferral = Referral::create(['referrer_id' => $referrer->id, 'referred_id' => $lurker->id]);

        $admin = User::factory()->create(['email' => config('admin.email')]);

        // Two referrals are short of the batch of 5, so only the one photo
        // bonus is owed (once, however many photos they posted).
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/referrals')
            ->assertJsonPath('data.leaderboard.0.eligibleAmount', 20);

        $this->partialMock(KopokopoTransferService::class, function ($mock) {
            $mock->shouldReceive('initiateTransfer')
                ->once()
                ->withArgs(fn($request) => (int) $request->input('amount') === 20)
                ->andReturn([true, 'Transfer initiated', [
                    'status' => 'success',
                    'location' => 'https://api.kopokopo.com/api/v1/send_money/bonus-transfer-1',
                ]]);
        });

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/referrals/{$referrer->id}/pay")
            ->assertOk()
            ->assertJsonPath('status', true);

        $postedReferral->refresh();
        $this->assertSame('bonus-transfer-1', $postedReferral->photo_bonus_reference);
        $this->assertEquals(20.0, (float) $postedReferral->photo_bonus_amount);
        $this->assertNull($postedReferral->photo_bonus_paid_at);
        // The batch reward is untouched, it hasn't been earned yet.
        $this->assertNull($postedReferral->kopokopo_reference);
        $this->assertNull($lurkerReferral->fresh()->photo_bonus_reference);

        // While that transfer is in flight, the bonus isn't owed again.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/referrals')
            ->assertJsonPath('data.leaderboard.0.eligibleAmount', 0);
    }

    public function test_one_transfer_pays_the_batch_reward_and_photo_bonuses_together(): void
    {
        $this->setThresholdAndReward(threshold: 2, rewardAmount: 100);
        Setting::query()->updateOrCreate(['key' => 'referral_photo_bonus_amount'], ['value' => 20]);

        $referrer = User::factory()->create(['phone' => '0700123456']);
        $poster = User::factory()->create();
        Photo::factory()->create(['user_id' => $poster->id]);
        Referral::create(['referrer_id' => $referrer->id, 'referred_id' => $poster->id]);
        Referral::create(['referrer_id' => $referrer->id, 'referred_id' => User::factory()->create()->id]);

        $this->partialMock(KopokopoTransferService::class, function ($mock) {
            $mock->shouldReceive('initiateTransfer')
                ->once()
                ->withArgs(fn($request) => (int) $request->input('amount') === 120)
                ->andReturn([true, 'Transfer initiated', [
                    'status' => 'success',
                    'location' => 'https://api.kopokopo.com/api/v1/send_money/combined-transfer',
                ]]);
        });

        $this->actingAs(User::factory()->create(['email' => config('admin.email')]), 'sanctum')
            ->postJson("/api/admin/referrals/{$referrer->id}/pay")
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertSame(2, Referral::where('kopokopo_reference', 'combined-transfer')->count());
        $this->assertSame(1, Referral::where('photo_bonus_reference', 'combined-transfer')->count());
    }

    public function test_a_confirmed_webhook_marks_photo_bonuses_paid_and_notifies_the_whole_amount(): void
    {
        Event::fake([KopokopoTransferInitiated::class]);

        $referrer = User::factory()->create(['phone' => '0700123456']);
        $both = Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => User::factory()->create()->id,
            'kopokopo_reference' => 'combined-transfer',
            'amount_paid' => 50,
            'photo_bonus_reference' => 'combined-transfer',
            'photo_bonus_amount' => 20,
        ]);
        $bonusOnly = Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => User::factory()->create()->id,
            'photo_bonus_reference' => 'combined-transfer',
            'photo_bonus_amount' => 20,
        ]);

        $this->postJson('/api/kopokopo-transfers', [
            'data' => [
                'id' => 'combined-transfer',
                'attributes' => [
                    'status' => 'Processed',
                    'created_at' => now()->toIso8601String(),
                    'currency' => 'KES',
                    'destinations' => [['amount' => 90]],
                    'transfer_batches' => [['status' => 'Transferred']],
                    'metadata' => [],
                ],
            ],
        ])->assertOk();

        $this->assertNotNull($both->fresh()->paid_at);
        $this->assertNotNull($both->fresh()->photo_bonus_paid_at);
        $this->assertNotNull($bonusOnly->fresh()->photo_bonus_paid_at);
        $this->assertNull($bonusOnly->fresh()->paid_at);

        Event::assertDispatchedTimes(KopokopoTransferInitiated::class, 1);
        Event::assertDispatched(
            KopokopoTransferInitiated::class,
            fn(KopokopoTransferInitiated $event): bool => $event->amount === 90.0,
        );
        $this->assertSame(90, Referral::summary()['totalPaidOut']);
    }

    public function test_a_failed_webhook_clears_the_photo_bonus_so_it_can_be_retried(): void
    {
        $referral = Referral::create([
            'referrer_id' => User::factory()->create(['phone' => '0700123456'])->id,
            'referred_id' => User::factory()->create()->id,
            'photo_bonus_reference' => 'bonus-transfer-2',
            'photo_bonus_amount' => 20,
        ]);

        $this->postJson('/api/kopokopo-transfers', [
            'data' => [
                'id' => 'bonus-transfer-2',
                'attributes' => [
                    'status' => 'Failed',
                    'created_at' => now()->toIso8601String(),
                    'currency' => 'KES',
                    'destinations' => [['amount' => 20]],
                    'metadata' => [],
                    'transfer_batches' => [[
                        'disbursements' => [['status' => 'Failed']],
                    ]],
                ],
            ],
        ])->assertOk();

        $referral->refresh();
        $this->assertNull($referral->photo_bonus_paid_at);
        $this->assertNull($referral->photo_bonus_reference);
        $this->assertNull($referral->photo_bonus_amount);
    }
}
