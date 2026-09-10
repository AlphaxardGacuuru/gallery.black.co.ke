<?php

namespace App\Http\Services;

use App\Models\User;

class OnboardingService extends Service
{
    public function completePermissionsStep(): array
    {
        $user = User::query()->findOrFail($this->id);

        // Merge rather than replace: settings may already hold unrelated
        // keys (theme, other future onboarding steps, ...).
        $settings = (array) ($user->settings ?? []);
        $settings['permissionsOnboardedAt'] = now()->toIso8601String();
        $user->settings = $settings;
        $user->save();

        return [true, 'Onboarding Updated', $user];
    }
}
