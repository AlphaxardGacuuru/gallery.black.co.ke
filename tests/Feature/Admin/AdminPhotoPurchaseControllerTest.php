<?php

namespace Tests\Feature\Admin;

use App\Http\Services\KopokopoTransferService;
use App\Models\Photo;
use App\Models\PhotoPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPhotoPurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => config('admin.email')]);
    }

    public function test_admin_can_set_the_sale_price_and_photographer_share(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/photo-purchases/settings', [
                'enabled' => true,
                'price' => 250,
                'photographerShare' => 70,
            ])
            ->assertOk();

        $this->assertSame(
            ['enabled' => true, 'price' => 250, 'photographerShare' => 70],
            Photo::saleSettings()
        );
        $this->assertSame(175, Photo::photographerShareOf(250));
    }

    public function test_the_photographer_share_cannot_exceed_100_percent(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/photo-purchases/settings', [
                'enabled' => true,
                'price' => 250,
                'photographerShare' => 120,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photographerShare');
    }

    public function test_non_admins_cannot_change_sale_settings(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/admin/photo-purchases/settings', [
                'enabled' => true,
                'price' => 250,
                'photographerShare' => 70,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_retry_a_photographer_payout(): void
    {
        $seller = User::factory()->create(['phone' => '0711222333']);
        $purchase = PhotoPurchase::factory()->paid()->create([
            'photo_id' => Photo::factory()->create(['user_id' => $seller->id])->id,
            'seller_amount' => 140,
        ]);

        $this->partialMock(KopokopoTransferService::class, function ($mock) {
            $mock->shouldReceive('initiateTransfer')
                ->once()
                ->withArgs(fn($request) => (int) $request->input('amount') === 140)
                ->andReturn([true, 'Transfer initiated', [
                    'status' => 'success',
                    'location' => 'https://api.kopokopo.com/api/v1/send_money/retry-payout',
                ]]);
        });

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/photo-purchases/{$purchase->id}/pay")
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.payoutReference', 'retry-payout');

        $this->assertSame('retry-payout', $purchase->fresh()->payout_reference);
    }

    public function test_an_unpaid_purchase_cannot_be_paid_out(): void
    {
        $purchase = PhotoPurchase::factory()->create();

        [$status, $message] = app(KopokopoTransferService::class)->payPhotographer($purchase);

        $this->assertFalse($status);
        $this->assertStringContainsString('hasn\'t paid', $message);
    }
}
