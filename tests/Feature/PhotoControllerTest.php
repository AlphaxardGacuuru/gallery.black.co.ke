<?php

namespace Tests\Feature;

use App\Jobs\GeneratePhotoImageThumbnailJob;
use App\Jobs\GeneratePhotoThumbnailJob;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoImage;
use App\Models\PhotoSlotPurchase;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
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
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
            'caption' => 'First entry',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
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
                'temporaryUploadIds' => [$this->temporaryUpload()->id],
                'caption' => 'First entry',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('temporaryUploadIds');
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
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
            'caption' => 'First entry',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
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
                'temporaryUploadIds' => [$this->temporaryUpload()->id],
                'caption' => $caption . ' entry',
            ])->assertCreated();
        }

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
            'caption' => 'Third entry',
        ])->assertUnprocessable();

        $this->assertSame(2, $competition->photos()->where('user_id', $user->id)->count());
    }

    public function test_submitting_more_photos_than_paid_slots_allow_fails(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $user = User::factory()->create(['phone' => '0700123456']);

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => [$this->temporaryUpload()->id, $this->temporaryUpload()->id],
            'caption' => 'Entry',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('temporaryUploadIds');
    }

    public function test_paid_extra_photo_slots_allow_a_multi_image_entry(): void
    {
        Storage::fake('public');
        Queue::fake();

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $user = User::factory()->create(['phone' => '0700123456']);

        PhotoSlotPurchase::factory()->paid()->extraPhoto()->count(2)->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
        ]);

        $ids = [
            $this->temporaryUpload()->id,
            $this->temporaryUpload()->id,
            $this->temporaryUpload()->id,
        ];

        $response = $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => $ids,
            'caption' => 'Entry',
        ])->assertCreated();

        $this->assertSame(1, Photo::where('user_id', $user->id)->count());
        $photo = Photo::where('user_id', $user->id)->firstOrFail();
        // 3 ids: the first becomes the Photo's own cover image, the
        // remaining 2 become PhotoImage rows at positions 1 and 2.
        $this->assertSame(2, PhotoImage::where('photo_id', $photo->id)->count());
        $this->assertSame([1, 2], $photo->images()->pluck('position')->all());
        $this->assertSame(0, TemporaryUpload::whereIn('id', $ids)->count());

        Queue::assertPushed(GeneratePhotoThumbnailJob::class, 1);
        Queue::assertPushed(GeneratePhotoImageThumbnailJob::class, 2);

        $response->assertJsonCount(2, 'data.images');
    }

    public function test_a_paid_extra_photo_purchase_does_not_grant_a_second_entry(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $user = User::factory()->create(['phone' => '0700123456']);

        PhotoSlotPurchase::factory()->paid()->extraPhoto()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
        ]);

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
            'caption' => 'First entry',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
            'caption' => 'Second entry',
        ])->assertUnprocessable();

        $this->assertSame(1, $competition->photos()->where('user_id', $user->id)->count());
    }

    public function test_duplicate_temporary_upload_ids_are_rejected(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $user = User::factory()->create(['phone' => '0700123456']);
        $uploadId = $this->temporaryUpload()->id;

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => [$uploadId, $uploadId],
            'caption' => 'Entry',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('temporaryUploadIds.1');
    }

    public function test_paid_extra_photo_slots_survive_deleting_and_resubmitting(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $user = User::factory()->create(['phone' => '0700123456']);

        PhotoSlotPurchase::factory()->paid()->extraPhoto()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
        ]);

        $response = $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
            'caption' => 'First entry',
        ])->assertCreated();

        $this->actingAs($user)
            ->deleteJson('/api/photos/' . $response->json('data.id'))
            ->assertOk();

        $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => [
                $this->temporaryUpload()->id,
                $this->temporaryUpload()->id,
            ],
            'caption' => 'Resubmitted entry',
        ])->assertCreated();

        $photo = Photo::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, $photo->images()->count());
    }

    public function test_destroying_a_photo_removes_its_extra_images_files_and_rows(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $user = User::factory()->create(['phone' => '0700123456']);

        PhotoSlotPurchase::factory()->paid()->extraPhoto()->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
        ]);

        $response = $this->actingAs($user)->postJson('/api/photos', [
            'temporaryUploadIds' => [
                $this->temporaryUpload()->id,
                $this->temporaryUpload()->id,
            ],
            'caption' => 'Entry',
        ])->assertCreated();

        $photoId = $response->json('data.id');
        $image = PhotoImage::where('photo_id', $photoId)->firstOrFail();
        $this->assertTrue(Storage::disk('public')->exists($image->path));

        $this->actingAs($user)->deleteJson("/api/photos/{$photoId}")->assertOk();

        $this->assertFalse(Storage::disk('public')->exists($image->path));
        $this->assertDatabaseMissing('photo_images', ['id' => $image->id]);
    }

    public function test_show_returns_images_in_order(): void
    {
        $competition = PhotoCompetition::factory()->create();
        $owner = User::factory()->create();
        $photo = Photo::factory()->create([
            'competition_id' => $competition->id,
            'user_id' => $owner->id,
        ]);
        PhotoImage::factory()->create(['photo_id' => $photo->id, 'position' => 2]);
        PhotoImage::factory()->create(['photo_id' => $photo->id, 'position' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/photos/{$photo->id}")
            ->assertOk()
            ->assertJsonPath('data.images.0.width', 1080)
            ->assertJsonCount(2, 'data.images');
    }

    public function test_owner_can_add_photos_to_an_already_submitted_entry(): void
    {
        Storage::fake('public');
        Queue::fake();

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $owner = User::factory()->create(['phone' => '0700123456']);
        PhotoSlotPurchase::factory()->paid()->extraPhoto()->create([
            'user_id' => $owner->id,
            'competition_id' => $competition->id,
        ]);

        $created = $this->actingAs($owner)->postJson('/api/photos', [
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
            'caption' => 'Entry',
        ])->assertCreated();

        $photoId = $created->json('data.id');

        $response = $this->actingAs($owner)->postJson("/api/photos/{$photoId}/images", [
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
        ])->assertCreated();

        $response->assertJsonCount(1, 'data.images');
        $this->assertSame(1, PhotoImage::where('photo_id', $photoId)->count());
        Queue::assertPushed(GeneratePhotoImageThumbnailJob::class, 1);
    }

    public function test_a_non_owner_cannot_add_photos_to_someone_elses_entry(): void
    {
        Storage::fake('public');

        $competition = PhotoCompetition::factory()->create(['status' => PhotoCompetition::STATUS_ACTIVE]);
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $photo = Photo::factory()->create([
            'competition_id' => $competition->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($intruder)
            ->postJson("/api/photos/{$photo->id}/images", [
                'temporaryUploadIds' => [$this->temporaryUpload()->id],
            ])
            ->assertForbidden();
    }

    public function test_photos_cannot_be_added_once_the_competition_has_ended(): void
    {
        Storage::fake('public');

        $competition = PhotoCompetition::factory()->ended()->create();
        $owner = User::factory()->create();
        $photo = Photo::factory()->create([
            'competition_id' => $competition->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/photos/{$photo->id}/images", [
                'temporaryUploadIds' => [$this->temporaryUpload()->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('temporaryUploadIds');
    }

    public function test_adding_photos_respects_the_remaining_capacity(): void
    {
        Storage::fake('public');

        $this->artisan('app:start-photo-competition');
        $competition = PhotoCompetition::first();
        $owner = User::factory()->create(['phone' => '0700123456']);
        // 1 paid slot => max 2 photos total; the entry already has 1 (the
        // cover), so only 1 more can be added.
        PhotoSlotPurchase::factory()->paid()->extraPhoto()->create([
            'user_id' => $owner->id,
            'competition_id' => $competition->id,
        ]);

        $created = $this->actingAs($owner)->postJson('/api/photos', [
            'temporaryUploadIds' => [$this->temporaryUpload()->id],
            'caption' => 'Entry',
        ])->assertCreated();

        $photoId = $created->json('data.id');

        $this->actingAs($owner)->postJson("/api/photos/{$photoId}/images", [
            'temporaryUploadIds' => [$this->temporaryUpload()->id, $this->temporaryUpload()->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('temporaryUploadIds');

        $this->assertSame(0, PhotoImage::where('photo_id', $photoId)->count());
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
