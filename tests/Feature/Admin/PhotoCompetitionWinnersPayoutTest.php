<?php

namespace Tests\Feature\Admin;

use App\Events\KopokopoTransferInitiated;
use App\Http\Services\KopokopoTransferService;
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
        // rest of payWinner()'s real logic run (guards, the prize_paid_at
        // update) while asserting the *request it builds* carries the
        // winner's real prize amount, not the KES 20 that was hardcoded
        // before this change.
        $this->partialMock(KopokopoTransferService::class, function ($mock) {
            $mock->shouldReceive('initiateTransfer')
                ->once()
                ->withArgs(fn($request) => (int) $request->input('amount') === 750)
                ->andReturn([true, 'Transfer Initiated', []]);
        });

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/photo-competition-winners/{$winner->id}/pay");

        $response->assertOk()->assertJsonPath('status', true);

        $this->assertNotNull($winner->fresh()->prize_paid_at);

        Event::assertDispatched(
            KopokopoTransferInitiated::class,
            fn(KopokopoTransferInitiated $event): bool => $event->amount === 750.0,
        );
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
}
