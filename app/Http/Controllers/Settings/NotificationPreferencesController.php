<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use Illuminate\Http\JsonResponse;

class NotificationPreferencesController extends Controller
{
    /**
     * Update which emails the user wants to receive — merged into the same
     * users.settings blob that also holds onboarding progress, so this only
     * touches the two notification keys and leaves the rest untouched.
     */
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->settings = array_merge((array) ($user->settings ?? []), $request->validated());
        $user->save();

        return response()->json([
            'saved' => true,
            'message' => __('Notification preferences updated.'),
        ]);
    }
}
