<?php

namespace Tests\Feature;

use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
