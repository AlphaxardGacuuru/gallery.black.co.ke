<?php

namespace Tests\Feature;

use App\Events\MpesaTransactionCreatedEvent;
use App\Http\Services\MPESATransactionService;
use App\Listeners\MpesaTransactionCreatedListener;
use App\Models\MPESATransaction;
use App\Models\PhoneVerification;
use App\Models\PhotoCompetition;
use App\Models\Setting;
use App\Models\TemporaryUpload;
use App\Models\User;
use App\Notifications\UserFlaggedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function changePhone(User $user, string $phone): void
    {
        $this->actingAs($user, 'sanctum')
            ->patchJson('/settings/profile', ['name' => $user->name, 'email' => $user->email, 'phone' => $phone])
            ->assertOk();
    }

    private function mockStkPush(string $reference = 'verify-123'): void
    {
        $this->partialMock(MPESATransactionService::class, function ($mock) use ($reference) {
            $mock->shouldReceive('stkPush')
                ->once()
                ->withArgs(fn ($request) => (int) $request->input('amount') === User::phoneVerificationFee())
                ->andReturn(['success', 'Request Sent to Your Phone', ['location' => "https://api.kopokopo.com/api/v1/incoming_payments/{$reference}"]]);
        });
    }

    private function pay(User $user, string $reference = 'verify-123'): void
    {
        $transaction = MPESATransaction::factory()->create([
            'user_id' => $user->id,
            'kopokopo_id' => $reference,
        ]);

        app(MpesaTransactionCreatedListener::class)
            ->handle(new MpesaTransactionCreatedEvent($transaction));
    }

    public function test_changing_phone_flags_the_account_without_notifying_it(): void
    {
        $user = User::factory()->create(['phone' => '0711223344']);

        $this->changePhone($user, '0799887766');

        $user->refresh();
        $this->assertTrue($user->isFlaggedUntilPhoneVerified());
        $this->assertSame(User::PHONE_CHANGE_FLAG_REASON, $user->flag_reason);
        Notification::assertNothingSentTo($user);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth')
            ->assertJsonPath('data.flaggedUntilPhoneVerified', true)
            ->assertJsonPath('data.phoneVerificationFee', User::DEFAULT_PHONE_VERIFICATION_FEE);
    }

    public function test_no_fee_lets_people_change_phone_freely(): void
    {
        Setting::query()->updateOrCreate(['key' => User::SETTING_PHONE_VERIFICATION_FEE], ['value' => 0]);
        $user = User::factory()->create(['phone' => '0711223344']);

        $this->changePhone($user, '0799887766');

        $this->assertFalse($user->fresh()->isFlagged());
    }

    public function test_paying_from_the_new_number_lifts_the_flag(): void
    {
        $user = User::factory()->create(['phone' => '0711223344']);
        $this->changePhone($user, '0799887766');
        $this->mockStkPush();

        $verificationId = $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/phone-verifications')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->json('data.verificationId');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/phone-verifications/{$verificationId}")
            ->assertJsonPath('data.status', 'pending');

        $this->pay($user);

        $this->assertFalse($user->fresh()->isFlagged());
        $this->assertSame('0799887766', PhoneVerification::query()->sole()->phone);
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/phone-verifications/{$verificationId}")
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_paying_for_a_number_since_replaced_keeps_the_flag(): void
    {
        $user = User::factory()->create(['phone' => '0711223344']);
        $this->changePhone($user, '0799887766');
        $this->mockStkPush();
        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/phone-verifications');

        $this->changePhone($user->fresh(), '0722334455');
        $this->pay($user);

        $this->assertTrue($user->fresh()->isFlaggedUntilPhoneVerified());
    }

    public function test_only_a_phone_change_flag_can_be_paid_off(): void
    {
        $user = User::factory()->create(['phone' => '0711223344']);
        $user->forceFill(['flag' => ['at' => now(), 'source' => User::FLAG_SOURCE_ADMIN]])->save();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/phone-verifications')
            ->assertJsonValidationErrors('phone');

        $this->assertSame(0, PhoneVerification::query()->count());
    }

    public function test_an_admin_can_still_flag_someone_waiting_to_verify(): void
    {
        $user = User::factory()->create(['phone' => '0711223344']);
        $this->changePhone($user, '0799887766');

        $this->actingAs(User::factory()->create(['email' => config('admin.email')]), 'sanctum')
            ->putJson("/api/admin/users/{$user->id}/flag", ['flagged' => true, 'reason' => 'Fake likes'])
            ->assertOk();

        $this->assertSame(User::FLAG_SOURCE_ADMIN, $user->fresh()->flag_source);
        Notification::assertSentTo($user, UserFlaggedNotification::class);
    }

    public function test_flagged_users_cannot_enter_the_challenge(): void
    {
        PhotoCompetition::factory()->create();
        $upload = TemporaryUpload::create(['disk' => 'public', 'path' => 'temporary-uploads/photos/a.jpg', 'original_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $user = User::factory()->create(['phone' => '0711223344']);
        $this->changePhone($user, '0799887766');

        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/photos', ['temporaryUploadIds' => [$upload->id], 'caption' => 'Hi'])
            ->assertJsonValidationErrors(['temporaryUploadIds' => 'Confirm your new M-Pesa phone number']);

        $user->forceFill(['flag' => ['at' => now(), 'source' => User::FLAG_SOURCE_ADMIN]])->save();

        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/photos', ['temporaryUploadIds' => [$upload->id], 'caption' => 'Hi'])
            ->assertJsonValidationErrors(['temporaryUploadIds' => 'Your account is flagged']);
    }

    public function test_admins_set_the_fee(): void
    {
        $this->actingAs(User::factory()->create(['email' => config('admin.email')]), 'sanctum')
            ->putJson('/api/admin/users/settings', ['phoneVerificationFee' => 25])
            ->assertOk();

        $this->assertSame(25, User::phoneVerificationFee());
        $this->assertTrue(User::screensNewSignups());
    }
}
