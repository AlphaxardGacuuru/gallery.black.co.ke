<?php

namespace App\Http\Services;

use App\Enums\UserEventName;
use App\Models\User;
use App\Services\EventRecorder;

class OnboardingService extends Service
{
    public function __construct(protected EventRecorder $eventRecorder)
    {
        parent::__construct();
    }

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
        $firstInstall = ! isset($settings['pwaInstalledAt']);
        $settings['pwaInstalledAt'] = now()->toIso8601String();
        $user->settings = $settings;
        $user->save();

        if ($firstInstall) {
            $this->eventRecorder->record(UserEventName::APP_INSTALLED, $user);
        }

        return [true, 'Onboarding Updated', $user];
    }
}
