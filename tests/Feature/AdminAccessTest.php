<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_are_forbidden_from_admin_routes(): void
    {
        $user = User::factory()->create(['email' => 'someone-else@example.com']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_guests_cannot_access_admin_routes(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_admin_can_view_dashboard_metrics(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);

        $competition = PhotoCompetition::factory()->create();
        Photo::factory()->create(['competition_id' => $competition->id, 'user_id' => $admin->id]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.totals.totalCompetitions', 1)
            ->assertJsonPath('data.totals.totalPhotos', 1);
    }
}
