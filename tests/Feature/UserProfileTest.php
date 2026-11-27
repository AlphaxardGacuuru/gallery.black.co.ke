<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_posts_likes_cash_won_and_leaderboard_position(): void
    {
        $competition = PhotoCompetition::factory()->create(['status' => PhotoCompetition::STATUS_ENDED]);
        $champion = User::factory()->create();
        $user = User::factory()->create();
        $championPhoto = Photo::factory()->create(['competition_id' => $competition->id, 'user_id' => $champion->id, 'likes_count' => 9]);
        $first = Photo::factory()->create(['competition_id' => $competition->id, 'user_id' => $user->id, 'likes_count' => 4]);
        Photo::factory()->create(['competition_id' => $competition->id, 'user_id' => $user->id, 'likes_count' => 3]);
        PhotoCompetitionWinner::query()->create(['competition_id' => $competition->id, 'photo_id' => $championPhoto->id, 'user_id' => $champion->id, 'position' => 1, 'prize_amount' => 500]);
        PhotoCompetitionWinner::query()->create(['competition_id' => $competition->id, 'photo_id' => $first->id, 'user_id' => $user->id, 'position' => 2, 'prize_amount' => 300]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/users/{$user->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.stats.posts', 2)
            ->assertJsonPath('data.stats.likes', 7)
            ->assertJsonPath('data.stats.cashWon', 300)
            ->assertJsonPath('data.stats.leaderboardPosition', 2);
    }

    public function test_someone_who_never_posted_has_no_leaderboard_position(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/users/{$user->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.stats.posts', 0)
            ->assertJsonPath('data.stats.leaderboardPosition', null);
    }

    public function test_profile_lists_only_that_users_photos(): void
    {
        $competition = PhotoCompetition::factory()->create();
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['competition_id' => $competition->id, 'user_id' => $user->id]);
        Photo::factory()->create(['competition_id' => $competition->id]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/users/{$user->id}/photos")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $photo->id);
    }

    public function test_profiles_need_an_account(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}/profile")
            ->assertUnauthorized();
    }

    public function test_users_can_save_and_unsave_a_photo(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create(['competition_id' => PhotoCompetition::factory()->create()->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/photos/{$photo->id}/bookmark")
            ->assertOk();
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/photos/{$photo->id}/bookmark")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/bookmarks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $photo->id)
            ->assertJsonPath('data.0.isBookmarkedByViewer', true);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/photos/{$photo->id}/bookmark")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/bookmarks')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_saved_photos_are_only_the_viewers_own(): void
    {
        $saver = User::factory()->create();
        $photo = Photo::factory()->create(['competition_id' => PhotoCompetition::factory()->create()->id]);
        $saver->photoBookmarks()->create(['photo_id' => $photo->id]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/bookmarks')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/users/{$photo->user_id}/photos")
            ->assertOk()
            ->assertJsonPath('data.0.isBookmarkedByViewer', false);
    }
}
