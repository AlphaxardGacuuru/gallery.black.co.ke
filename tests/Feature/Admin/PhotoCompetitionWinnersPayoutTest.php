<?php

namespace Tests\Feature\Admin;

use App\Events\KopokopoTransferInitiated;
use App\Http\Services\KopokopoTransferService;
use App\Http\Services\MPESATransactionService;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PhotoCompetitionWinnersPayoutTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => config('admin.email')]);
    }

    public function test_admin_can_view_recent_competitions_with_ranked_winners(): void
    {
        $competition = PhotoCompetition::factory()->create();
        $second = PhotoCompetitionWinner::factory()->create([
            'competition_id' => $competition->id,
            'position' => 2,
            'prize_amount' => 300,
        ]);
        $first = PhotoCompetitionWinner::factory()->create([
            'competition_id' => $competition->id,
            'position' => 1,
            'prize_amount' => 500,
        ]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/photo-competitions/recent');

        $response->assertOk()
            ->assertJsonPath('data.0.winners.0.id', $first->id)
            ->assertJsonPath('data.0.winners.0.position', 1)
            ->assertJsonPath('data.0.winners.1.id', $second->id)
            ->assertJsonPath('data.0.winners.1.position', 2);
    }

    public function test_admin_can_pay_an_unpaid_winner(): void
    {
        Event::fake([KopokopoTransferInitiated::class]);

        $user = User::factory()->create(['phone' => '0700123456']);
        $winner = PhotoCompetitionWinner::factory()->create([
            'user_id' => $user->id,
            'prize_amount' => 750,
        ]);

        // The real Kopokopo/M-Pesa network call happens inside
        // initiateTransfer() via a fresh K2 SDK client the service builds
        // itself (no DI seam) — partial-mocking just that boundary lets the
        // rest of payWinner()'s real logic run (guards, the kopokopo_reference
        // update) while asserting the *request it builds* carries the
        // winner's real prize amount, not the KES 20 that was hardcoded
        // before this change.
        $this->partialMock(KopokopoTransferService::class, function ($mock) {
            $mock->shouldReceive('initiateTransfer')
                ->once()
                ->withArgs(fn($request) => (int) $request->input('amount') === 750)
                ->andReturn([true, 'Transfer initiated, awaiting confirmation from Kopokopo', [
                    'status' => 'success',
                    'location' => 'https://api.kopokopo.com/api/v1/send_money/winner-transfer-123',
                ]]);
        });

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/photo-competition-winners/{$winner->id}/pay");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.kopokopoReference', 'winner-transfer-123');

        $winner->refresh();
        $this->assertNull($winner->prize_paid_at);
        $this->assertSame('winner-transfer-123', $winner->kopokopo_reference);

        // Paying it off is only initiated here, it isn't confirmed paid,
        // and the recipient isn't notified, until Kopokopo's webhook
        // confirms the transfer actually completed (see
        // test_a_confirmed_webhook_marks_the_winner_paid_and_notifies_them
        // below).
        Event::assertNotDispatched(KopokopoTransferInitiated::class);
    }

    public function test_a_second_pay_attempt_is_rejected_while_one_is_already_in_progress(): void
    {
        $winner = PhotoCompetitionWinner::factory()->create([
            'user_id' => User::factory()->create(['phone' => '0700123456'])->id,
            'kopokopo_reference' => 'already-in-flight',
        ]);

        $service = app(KopokopoTransferService::class);
        [$status, $message] = $service->payWinner($winner);

        $this->assertFalse($status);
        $this->assertStringContainsString('already in progress', $message);
    }

    public function test_a_confirmed_webhook_marks_the_winner_paid_and_notifies_them(): void
    {
        Event::fake([KopokopoTransferInitiated::class]);

        $user = User::factory()->create(['phone' => '0700123456']);
        $winner = PhotoCompetitionWinner::factory()->create([
            'user_id' => $user->id,
            'prize_amount' => 750,
            'kopokopo_reference' => 'winner-transfer-123',
        ]);

        $this->postJson('/api/kopokopo-transfers', [
            'data' => [
                'id' => 'winner-transfer-123',
                'attributes' => [
                    'status' => 'Processed',
                    'created_at' => now()->toIso8601String(),
                    'currency' => 'KES',
                    'destinations' => [['amount' => 750]],
                    'transfer_batches' => [['status' => 'Transferred']],
                    'metadata' => [],
                ],
            ],
        ])->assertOk();

        $this->assertNotNull($winner->fresh()->prize_paid_at);

        Event::assertDispatched(
            KopokopoTransferInitiated::class,
            fn(KopokopoTransferInitiated $event): bool => $event->amount === 750.0,
        );
    }

    public function test_a_failed_webhook_clears_the_reference_so_the_winner_can_be_retried(): void
    {
        $winner = PhotoCompetitionWinner::factory()->create([
            'user_id' => User::factory()->create(['phone' => '0700123456'])->id,
            'kopokopo_reference' => 'winner-transfer-456',
        ]);

        $this->postJson('/api/kopokopo-transfers', [
            'data' => [
                'id' => 'winner-transfer-456',
                'attributes' => [
                    'status' => 'Failed',
                    'created_at' => now()->toIso8601String(),
                    'currency' => 'KES',
                    'destinations' => [['amount' => 750]],
                    'metadata' => [],
                    'transfer_batches' => [[
                        'disbursements' => [['status' => 'Failed']],
                    ]],
                ],
            ],
        ])->assertOk();

        $winner->refresh();
        $this->assertNull($winner->prize_paid_at);
        $this->assertNull($winner->kopokopo_reference);
    }

    public function test_paying_an_already_paid_winner_fails_without_a_transfer(): void
    {
        $winner = PhotoCompetitionWinner::factory()->create([
            'user_id' => User::factory()->create(['phone' => '0700123456'])->id,
            'prize_paid_at' => now(),
        ]);

        $service = app(KopokopoTransferService::class);
        [$status, $message] = $service->payWinner($winner);

        $this->assertFalse($status);
        $this->assertStringContainsString('already been paid', $message);
    }

    public function test_paying_a_winner_without_a_phone_fails(): void
    {
        $winner = PhotoCompetitionWinner::factory()->create([
            'user_id' => User::factory()->create(['phone' => null])->id,
        ]);

        $service = app(KopokopoTransferService::class);
        [$status, $message] = $service->payWinner($winner);

        $this->assertFalse($status);
        $this->assertStringContainsString('no M-Pesa phone number', $message);
    }

    public function test_admin_can_update_prize_tiers(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/photo-competitions/prize-tiers', [
                'prizeTiers' => [1000, 500, 250],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.prizeTiers', [1000, 500, 250, 0, 0, 0, 0, 0, 0, 0]);

        $this->assertSame(
            [1000, 500, 250, 0, 0, 0, 0, 0, 0, 0],
            Setting::query()->where('key', 'photo_prize_tiers')->value('value')
        );
    }

    public function test_prize_tiers_validation_rejects_negative_or_over_ten_entries(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/photo-competitions/prize-tiers', [
                'prizeTiers' => [-1],
            ])
            ->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/photo-competitions/prize-tiers', [
                'prizeTiers' => array_fill(0, 11, 100),
            ])
            ->assertUnprocessable();
    }

    public function test_admin_can_update_extra_slot_settings(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/photo-competitions/extra-slot', [
                'enabled' => true,
                'price' => 100,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.price', 100);

        $this->assertEqualsCanonicalizing(
            ['enabled' => true, 'price' => 100],
            Setting::query()->where('key', 'photo_extra_slot')->value('value')
        );
    }

    public function test_extra_slot_validation_rejects_a_negative_price(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/photo-competitions/extra-slot', [
                'enabled' => true,
                'price' => -1,
            ])
            ->assertUnprocessable();
    }

    public function test_admin_can_update_extra_photo_settings(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/photo-competitions/extra-photo', [
                'enabled' => true,
                'price' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.price', 20);

        $this->assertEqualsCanonicalizing(
            ['enabled' => true, 'price' => 20],
            Setting::query()->where('key', 'photo_extra_photo')->value('value')
        );
    }

    public function test_extra_photo_validation_rejects_a_negative_price(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/photo-competitions/extra-photo', [
                'enabled' => true,
                'price' => -1,
            ])
            ->assertUnprocessable();
    }

    public function test_non_admins_cannot_update_extra_photo_settings(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/admin/photo-competitions/extra-photo', [
                'enabled' => true,
                'price' => 20,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_active_competition_end_time_without_a_prize_field(): void
    {
        $competition = PhotoCompetition::factory()->create([
            'status' => PhotoCompetition::STATUS_ACTIVE,
        ]);
        $newEndsAt = now()->addDays(2)->setMicroseconds(0);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/photo-competitions/active', [
                'endsAt' => $newEndsAt->toIso8601String(),
            ]);

        $response->assertOk();
        $this->assertTrue($newEndsAt->equalTo($competition->fresh()->ends_at));
    }

    /**
     * Two payable winners (500 + 300), plus one of each kind that shouldn't
     * count: already paid, payout in flight, seed account, and no phone.
     */
    private function competitionWithMixedWinners(): PhotoCompetition
    {
        $competition = PhotoCompetition::factory()->create();
        $winner = fn(int $position, int $prize, array $attributes = [], array $user = []) => PhotoCompetitionWinner::factory()->create([
            'competition_id' => $competition->id,
            'position' => $position,
            'prize_amount' => $prize,
            'user_id' => User::factory()->create(['phone' => "070012345{$position}", ...$user])->id,
            ...$attributes,
        ]);

        $winner(1, 500);
        $winner(2, 300);
        $winner(3, 200, ['prize_paid_at' => now()]);
        $winner(4, 100, ['kopokopo_reference' => 'in-flight']);
        $winner(5, 100, [], ['is_seed' => true]);
        $winner(6, 100, [], ['phone' => null]);

        return $competition;
    }

    public function test_recent_competitions_include_what_the_wallet_needs_for_outstanding_prizes(): void
    {
        $this->competitionWithMixedWinners();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/photo-competitions/recent')
            ->assertOk()
            ->assertJsonPath('data.0.payoutTotals', [
                'payouts' => 2,
                'prizes' => 800,
                'fees' => 2 * KopokopoTransferService::TRANSFER_FEE,
                'total' => 800 + 2 * KopokopoTransferService::TRANSFER_FEE,
            ]);
    }

    public function test_loading_the_wallet_stk_pushes_the_admin_wallet_number_for_the_total(): void
    {
        $competition = $this->competitionWithMixedWinners();
        $expected = 800 + 2 * KopokopoTransferService::TRANSFER_FEE;

        $this->partialMock(MPESATransactionService::class, function ($mock) use ($expected) {
            $mock->shouldReceive('stkPush')
                ->once()
                ->withArgs(fn($request, $phone) => (int) $request->input('amount') === $expected
                    && $phone === '0700364446')
                ->andReturn(['success', 'Request Sent to Your Phone', ['location' => 'https://x/incoming_payments/abc']]);
        });

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/photo-competitions/{$competition->id}/load-wallet", ['amount' => 1])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.amount', $expected);
    }

    public function test_loading_the_wallet_does_nothing_when_no_prizes_are_outstanding(): void
    {
        $competition = PhotoCompetition::factory()->create();
        PhotoCompetitionWinner::factory()->create(['competition_id' => $competition->id, 'prize_paid_at' => now()]);

        $this->partialMock(MPESATransactionService::class, fn($mock) => $mock->shouldNotReceive('stkPush'));

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/photo-competitions/{$competition->id}/load-wallet")
            ->assertOk()
            ->assertJsonPath('status', false);
    }

    public function test_non_admins_cannot_load_the_wallet(): void
    {
        $competition = PhotoCompetition::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admin/photo-competitions/{$competition->id}/load-wallet")
            ->assertForbidden();
    }
}
