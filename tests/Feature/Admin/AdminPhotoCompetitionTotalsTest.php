<?php

namespace Tests\Feature\Admin;

use App\Models\Photo;
use App\Models\PhotoLike;
use App\Models\PhotoSlotPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPhotoCompetitionTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_count_paid_extra_entry_and_extra_photo_purchases(): void
    {
        PhotoSlotPurchase::factory()->paid()->create();
        PhotoSlotPurchase::factory()->paid()->extraPhoto()->create();
        PhotoSlotPurchase::factory()->create();
        PhotoSlotPurchase::factory()->create(['status' => PhotoSlotPurchase::STATUS_FAILED]);

        $admin = User::factory()->create(['email' => config('admin.email')]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/photo-competitions')
            ->assertOk()
            ->assertJsonPath('data.totals.totalSlotPurchases', 2);
    }

    public function test_totals_leave_out_seed_users_their_photos_and_likes(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $real = User::factory()->create();
        $seed = User::factory()->create(['is_seed' => true]);

        $realPhoto = Photo::factory()->for($real)->create(['likes_count' => 2]);
        $seedPhoto = Photo::factory()->for($seed)->create(['likes_count' => 2]);

        // Only a real user's like on a real user's photo counts.
        PhotoLike::factory()->for($realPhoto)->for($real)->create();
        PhotoLike::factory()->for($realPhoto)->for($seed)->create();
        PhotoLike::factory()->for($seedPhoto)->for($real)->create();
        PhotoLike::factory()->for($seedPhoto)->for($seed)->create();

        PhotoSlotPurchase::factory()->paid()->for($real)->create();
        PhotoSlotPurchase::factory()->paid()->for($seed)->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/photo-competitions')
            ->assertOk()
            ->assertJsonPath('data.totals.totalPhotos', 1)
            ->assertJsonPath('data.totals.totalLikes', 1)
            ->assertJsonPath('data.totals.totalSlotPurchases', 1);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.totals.totalUsers', 2)
            ->assertJsonPath('data.totals.totalPhotos', 1)
            ->assertJsonPath('data.totals.totalLikes', 1)
            ->assertJsonPath('data.dailyVolume.13.submitted', 1);
    }
}
