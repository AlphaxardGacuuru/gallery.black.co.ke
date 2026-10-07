<?php

namespace App\Http\Services;

use App\Models\User;

class OnboardingService extends Service
{
    public function completeInstallStep(): array
    {
        $user = User::query()->findOrFail($this->id);

        // Merge rather than replace: settings may already hold unrelated
        // keys (theme, other future onboarding steps, ...).
        $settings = (array) ($user->settings ?? []);
        $settings['installOnboardedAt'] = now()->toIso8601String();
        $user->settings = $settings;
        $user->save();

        return [true, 'Onboarding Updated', $user];
    }

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

    public function completeReferralStep(): array
    {
        $user = User::query()->findOrFail($this->id);

        // Merge rather than replace: settings may already hold unrelated
        // keys (theme, other future onboarding steps, ...).
        $settings = (array) ($user->settings ?? []);
        $settings['referralOnboardedAt'] = now()->toIso8601String();
        $user->settings = $settings;
        $user->save();

        return [true, 'Onboarding Updated', $user];
    }

    /**
     * Distinct from installOnboardedAt (which just means the install prompt
     * was shown/resolved, however it was resolved): this is only ever
     * called from the frontend's real `appinstalled` event or an "accepted"
     * install-prompt outcome, so its presence genuinely means the PWA is
     * installed (admin's "Installed" column relies on that distinction).
     */
    public function recordPwaInstalled(): array
    {
        $user = User::query()->findOrFail($this->id);

        $settings = (array) ($user->settings ?? []);
        $settings['pwaInstalledAt'] = now()->toIso8601String();
        $user->settings = $settings;
        $user->save();

        return [true, 'Onboarding Updated', $user];
    }
}
