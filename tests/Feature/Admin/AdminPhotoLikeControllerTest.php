<?php

namespace Tests\Feature\Admin;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPhotoLikeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => config('admin.email')]);
    }

    public function test_a_non_admin_cannot_manage_likes(): void
    {
        $photo = Photo::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/photos/{$photo->id}/likes")
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/photos/{$photo->id}/likes/{$user->id}")
            ->assertForbidden();
    }

    public function test_admin_can_list_users_annotated_with_like_status(): void
    {
        $photo = Photo::factory()->create();
        $liker = User::factory()->create(['name' => 'Alice Liker']);
        $nonLiker = User::factory()->create(['name' => 'Bob Bystander']);
        PhotoLike::create(['photo_id' => $photo->id, 'user_id' => $liker->id]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/admin/photos/{$photo->id}/likes");

        $response->assertOk();

        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($byId[$liker->id]['likesPhoto']);
        $this->assertFalse($byId[$nonLiker->id]['likesPhoto']);
    }

    public function test_admin_can_search_users_by_name(): void
    {
        $photo = Photo::factory()->create();
        User::factory()->create(['name' => 'Alice Liker']);
        User::factory()->create(['name' => 'Bob Bystander']);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/admin/photos/{$photo->id}/likes?name=Alice");

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Alice Liker', $response->json('data.0.name'));
    }

    public function test_admin_can_add_a_like_on_behalf_of_a_user(): void
    {
        $photo = Photo::factory()->create(['likes_count' => 0]);
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/photos/{$photo->id}/likes/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.liked', true)
            ->assertJsonPath('data.likesCount', 1);

        $this->assertSame(1, $photo->fresh()->likes_count);
        $this->assertTrue(
            PhotoLike::where('photo_id', $photo->id)->where('user_id', $user->id)->exists()
        );
    }

    public function test_admin_can_remove_a_like_on_behalf_of_a_user(): void
    {
        $photo = Photo::factory()->create(['likes_count' => 1]);
        $user = User::factory()->create();
        PhotoLike::create(['photo_id' => $photo->id, 'user_id' => $user->id]);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/photos/{$photo->id}/likes/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.liked', false)
            ->assertJsonPath('data.likesCount', 0);

        $this->assertSame(0, $photo->fresh()->likes_count);
        $this->assertFalse(
            PhotoLike::where('photo_id', $photo->id)->where('user_id', $user->id)->exists()
        );
    }

    public function test_admin_can_add_a_like_even_after_the_competition_has_ended(): void
    {
        $competition = PhotoCompetition::factory()->ended()->create();
        $photo = Photo::factory()->create([
            'competition_id' => $competition->id,
            'likes_count' => 0,
        ]);
        $user = User::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/photos/{$photo->id}/likes/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.liked', true);

        $this->assertSame(1, $photo->fresh()->likes_count);
    }
}
