<?php

namespace Tests\Feature;

use App\Http\Services\OnboardingService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_the_permissions_step_records_a_timestamp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');
        [$status, $message, $updated] = (new OnboardingService)->completePermissionsStep();

        $this->assertTrue($status);
        $this->assertSame('Onboarding Updated', $message);
        $this->assertNotEmpty($updated->settings->permissionsOnboardedAt ?? null);
    }

    public function test_completing_the_permissions_step_preserves_other_settings(): void
    {
        $user = User::factory()->create(['settings' => ['theme' => 'dark']]);

        $this->actingAs($user, 'sanctum');
        [, , $updated] = (new OnboardingService)->completePermissionsStep();

        $this->assertSame('dark', $updated->settings->theme);
        $this->assertNotEmpty($updated->settings->permissionsOnboardedAt);
    }

    public function test_completing_the_permissions_step_only_affects_the_authenticated_user(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($me, 'sanctum');
        (new OnboardingService)->completePermissionsStep();

        $this->assertNull($other->fresh()->settings->permissionsOnboardedAt ?? null);
    }
}
