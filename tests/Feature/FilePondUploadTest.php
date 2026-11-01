<?php

namespace Tests\Feature;

use App\Models\PhotoCompetition;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FilePondUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function uploadPhoto(User $user): TemporaryUpload
    {
        $id = $this->actingAs($user, 'sanctum')
            ->post('/api/filepond/photos', ['filepond-photo' => UploadedFile::fake()->image('photo.jpg')])
            ->assertOk()
            ->getContent();

        return TemporaryUpload::findOrFail($id);
    }

    public function test_guests_cannot_upload(): void
    {
        $this->postJson('/api/filepond/support-tickets/attachments', [
            'filepond-support-ticket-attachments' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
            ->assertUnauthorized();

        $this->postJson('/api/filepond/photos', ['filepond-photo' => UploadedFile::fake()->image('photo.jpg')])
            ->assertUnauthorized();

        $this->assertDatabaseEmpty('temporary_uploads');
    }

    public function test_uploads_record_their_owner(): void
    {
        $user = User::factory()->create();

        $this->assertSame($user->id, $this->uploadPhoto($user)->user_id);
    }

    public function test_users_cannot_delete_someone_elses_upload(): void
    {
        $upload = $this->uploadPhoto(User::factory()->create());

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/filepond/photos/{$upload->id}")
            ->assertOk();

        $this->assertModelExists($upload);
        Storage::disk('public')->assertExists($upload->path);
    }

    public function test_users_cannot_submit_someone_elses_upload(): void
    {
        $this->artisan('app:start-photo-competition');
        $upload = $this->uploadPhoto(User::factory()->create());

        $this->actingAs(User::factory()->create(['phone' => '0700123456']), 'sanctum')
            ->postJson('/api/photos', ['temporaryUploadIds' => [$upload->id], 'caption' => 'Not mine'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('temporaryUploadIds');

        $this->assertModelExists($upload);
        $this->assertSame(0, PhotoCompetition::first()->photos()->count());
    }

    public function test_avatars_cannot_be_replaced_through_filepond(): void
    {
        $victim = User::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->post("/api/filepond/avatar/{$victim->id}", ['filepond-avatar' => UploadedFile::fake()->image('a.jpg')])
            ->assertMethodNotAllowed();

        $this->assertSame($victim->avatar, $victim->fresh()->avatar);
    }
}
