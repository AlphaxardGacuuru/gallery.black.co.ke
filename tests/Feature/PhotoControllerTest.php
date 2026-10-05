<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function temporaryUpload(): TemporaryUpload
    {
        $file = UploadedFile::fake()->image('photo.jpg');
        $path = $file->store('temporary-uploads/photos', 'public');

        return TemporaryUpload::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function test_second_submission_fails_without_a_paid_extra_slot(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $user = User::factory()->create(['phone' => '0700123456']);

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadId' => $this->temporaryUpload()->id,
            'caption' => 'First entry',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadId' => $this->temporaryUpload()->id,
            'caption' => 'Second entry',
        ])->assertUnprocessable();

        $this->assertSame(1, $competition->photos()->where('user_id', $user->id)->count());
    }

    public function test_a_concurrent_submission_is_rejected_instead_of_double_submitting(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $user = User::factory()->create(['phone' => '0700123456']);

        // Simulates a second, truly concurrent request (e.g. two open tabs)
        // already holding the per-user-per-competition lock when this one
        // arrives, instead of racing it in-process (which PHPUnit can't do).
        $lock = Cache::lock("photo-submission:{$competition->id}:{$user->id}", 10);
        $lock->get();

        try {
            $this->actingAs($user)->postJson('/api/photos', [
                'temporaryUploadId' => $this->temporaryUpload()->id,
                'caption' => 'First entry',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('temporaryUploadId');
        } finally {
            $lock->release();
        }

        $this->assertSame(0, $competition->photos()->where('user_id', $user->id)->count());
    }

    public function test_second_submission_succeeds_with_a_paid_extra_slot(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $user = User::factory()->create(['phone' => '0700123456']);

        PhotoSlotPurchase::factory()->paid()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
        ]);

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadId' => $this->temporaryUpload()->id,
            'caption' => 'First entry',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadId' => $this->temporaryUpload()->id,
            'caption' => 'Second entry',
        ])->assertCreated();

        $this->assertSame(2, $competition->photos()->where('user_id', $user->id)->count());
    }

    public function test_third_submission_fails_even_with_a_paid_extra_slot(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $user = User::factory()->create(['phone' => '0700123456']);

        PhotoSlotPurchase::factory()->paid()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
        ]);

        foreach (['First', 'Second'] as $caption) {
            $this->actingAs($user)->postJson('/api/photos', [
                'temporaryUploadId' => $this->temporaryUpload()->id,
                'caption' => $caption . ' entry',
            ])->assertCreated();
        }

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadId' => $this->temporaryUpload()->id,
            'caption' => 'Third entry',
        ])->assertUnprocessable();

        $this->assertSame(2, $competition->photos()->where('user_id', $user->id)->count());
    }

    public function test_show_lets_any_authenticated_user_view_a_photo(): void
    {
        $competition = PhotoCompetition::factory()->create();
        $owner = User::factory()->create(['name' => 'Owner']);
        $viewer = User::factory()->create();
        $photo = Photo::factory()->create([
            'competition_id' => $competition->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/photos/{$photo->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $photo->id)
            ->assertJsonPath('data.userName', 'Owner')
            ->assertJsonPath('canDelete', false)
            ->assertJsonPath('canLike', true);
    }

    public function test_show_allows_the_owner_to_delete_only_while_the_competition_is_active(): void
    {
        $activeCompetition = PhotoCompetition::factory()->create();
        $owner = User::factory()->create();
        $photo = Photo::factory()->create([
            'competition_id' => $activeCompetition->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/photos/{$photo->id}")
            ->assertOk()
            ->assertJsonPath('canDelete', true)
            ->assertJsonPath('canLike', true);

        $endedCompetition = PhotoCompetition::factory()->ended()->create();
        $endedPhoto = Photo::factory()->create([
            'competition_id' => $endedCompetition->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/photos/{$endedPhoto->id}")
            ->assertOk()
            ->assertJsonPath('canDelete', false)
            ->assertJsonPath('canLike', false);
    }

    public function test_show_returns_not_found_for_a_missing_photo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/photos/does-not-exist')
            ->assertNotFound();
    }
}
