<?php

namespace Tests\Feature\Settings;

use App\Http\Services\AuthService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class ProfileAvatarUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_user_can_upload_a_profile_picture(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('me.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $path = $user
            ->refresh()
            ->getRawOriginal('avatar');

        $this->assertStringStartsWith(User::AVATAR_UPLOAD_DIRECTORY . '/', $path);
        $this->assertTrue($user->hasUploadedAvatar());
        Storage::disk('public')->assertExists($path);
    }

    public function test_uploading_a_new_picture_deletes_the_previous_upload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('first.jpg'),
            ])
            ->assertOk();

        $first = $user
            ->refresh()
            ->getRawOriginal('avatar');

        $this->actingAs($user, 'sanctum')
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('second.png'),
            ])
            ->assertOk();

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($user->refresh()->getRawOriginal('avatar'));
    }

    public function test_the_upload_must_be_a_small_enough_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->actingAs($user, 'sanctum')
            ->postJson(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()
                    ->image('huge.jpg')
                    ->size(User::MAX_AVATAR_KB + 1),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->assertFalse($user->refresh()->hasUploadedAvatar());
    }

    public function test_guests_cannot_upload_a_profile_picture(): void
    {
        $this->postJson(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ])
            ->assertUnauthorized();
    }

    public function test_google_sign_in_keeps_an_uploaded_picture(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('me.jpg'),
            ])
            ->assertOk();

        $uploaded = $user
            ->refresh()
            ->getRawOriginal('avatar');

        $googleUser = (new SocialiteUser)
            ->setRaw([])
            ->map([
                'id' => 'google-1',
                'email' => $user->email,
                'name' => $user->name,
                'avatar' => 'https://lh3.googleusercontent.com/a/new-avatar',
            ]);

        app(AuthService::class)
            ->findOrCreateFromSocialite($googleUser, null);

        $this->assertSame($uploaded, $user->refresh()->getRawOriginal('avatar'));
    }
}
