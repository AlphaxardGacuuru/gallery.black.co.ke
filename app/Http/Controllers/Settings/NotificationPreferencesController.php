<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Http\Services\UserService;
use Illuminate\Http\JsonResponse;

class NotificationPreferencesController extends Controller
{
    public function __construct(protected UserService $userService)
    {
        //
    }

    /**
     * Update which emails the user wants to receive — merged into the same
     * users.settings blob that also holds onboarding progress, so this only
     * touches the two notification keys and leaves the rest untouched.
     */
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $this->userService->updateNotificationPreferences($request->user(), $request->validated());

        return response()->json([
            'saved' => true,
            'message' => __('Notification preferences updated.'),
        ]);
    }
}
