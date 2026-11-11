<?php

namespace Tests\Feature\Admin;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoLike;
use App\Models\PhotoPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUserDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->create(['email' => config('admin.email')]);
    }

    public function test_deleting_removes_the_account_its_photos_and_their_files(): void
    {
        $user = User::factory()->create();
        $path = UploadedFile::fake()
            ->image('photo.jpg')
            ->store('photos', 'public');
        $photo = Photo::factory()->create(['user_id' => $user->id, 'path' => $path]);
        $user->createToken('spa');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('message', "{$user->name} deleted");

        $this->assertModelMissing($user);
        $this->assertModelMissing($photo);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_likes_from_a_deleted_user_stop_counting(): void
    {
        $user = User::factory()->create();
        $photo = Photo::factory()->create([
            'competition_id' => PhotoCompetition::factory()->create()->id,
            'likes_count' => 2,
        ]);
        PhotoLike::create(['photo_id' => $photo->id, 'user_id' => $user->id]);
        PhotoLike::create(['photo_id' => $photo->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$user->id}")
            ->assertOk();

        $this->assertSame(1, $photo->fresh()->likes_count);
    }

    public function test_users_with_payment_records_cannot_be_deleted(): void
    {
        $winner = PhotoCompetitionWinner::factory()->create();
        $buyer = PhotoPurchase::factory()
            ->paid()
            ->create()
            ->buyer;

        collect([$winner->user, $buyer])->each(fn (User $user) => $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$user->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user'));

        $this->assertModelExists($winner->user);
        $this->assertModelExists($buyer);
    }

    public function test_admins_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$this->admin->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');

        $this->assertModelExists($this->admin);
    }

    public function test_non_admins_cannot_delete_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admin/users/{$user->id}")
            ->assertForbidden();

        $this->assertModelExists($user);
    }
}
