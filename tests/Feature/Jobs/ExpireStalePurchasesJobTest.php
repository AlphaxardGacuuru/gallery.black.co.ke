<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExpireStalePurchasesJob;
use App\Models\PhotoPurchase;
use App\Models\PhotoSlotPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireStalePurchasesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expires_purchases_left_pending_too_long(): void
    {
        $staleSlot = PhotoSlotPurchase::factory()->create(['created_at' => now()->subMinutes(20)]);
        $staleSale = PhotoPurchase::factory()->create(['created_at' => now()->subMinutes(20)]);

        (new ExpireStalePurchasesJob)->handle();

        $this->assertSame(PhotoSlotPurchase::STATUS_EXPIRED, $staleSlot->fresh()->status);
        $this->assertSame(PhotoPurchase::STATUS_EXPIRED, $staleSale->fresh()->status);
    }

    public function test_it_leaves_recent_and_settled_purchases_alone(): void
    {
        $recent = PhotoSlotPurchase::factory()->create(['created_at' => now()->subMinutes(5)]);
        $paid = PhotoSlotPurchase::factory()->paid()->create(['created_at' => now()->subHour()]);
        $failed = PhotoPurchase::factory()->create([
            'status' => PhotoPurchase::STATUS_FAILED,
            'created_at' => now()->subHour(),
        ]);

        (new ExpireStalePurchasesJob)->handle();

        $this->assertSame(PhotoSlotPurchase::STATUS_PENDING, $recent->fresh()->status);
        $this->assertSame(PhotoSlotPurchase::STATUS_PAID, $paid->fresh()->status);
        $this->assertSame(PhotoPurchase::STATUS_FAILED, $failed->fresh()->status);
    }

    public function test_the_buyer_sees_an_unanswered_prompt_as_expired_before_the_job_runs(): void
    {
        $fresh = PhotoSlotPurchase::factory()->create();
        $stale = PhotoSlotPurchase::factory()->create([
            'user_id' => $fresh->user_id,
            'created_at' => now()->subMinutes(PhotoSlotPurchase::PENDING_TTL_MINUTES + 1),
        ]);

        $this->actingAs($fresh->user, 'sanctum')
            ->getJson("/api/photo-slot-purchases/{$fresh->id}")
            ->assertJsonPath('data.status', PhotoSlotPurchase::STATUS_PENDING);

        $this->actingAs($fresh->user, 'sanctum')
            ->getJson("/api/photo-slot-purchases/{$stale->id}")
            ->assertJsonPath('data.status', PhotoSlotPurchase::STATUS_EXPIRED);

        $this->assertSame(PhotoSlotPurchase::STATUS_PENDING, $stale->fresh()->status);
    }
}
