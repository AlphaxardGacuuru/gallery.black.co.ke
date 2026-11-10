<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileAvatarUpdateRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Services\UserService;
use App\Support\Spa;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function __construct(protected UserService $userService)
    {
        //
    }

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): View
    {
        return Spa::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $this->userService->updateProfile($request->user(), $request->validated());

        return response()->json([
            'saved' => true,
            'message' => __('Profile updated.'),
        ], 200);
    }

    /**
     * Replace the user's profile picture.
     */
    public function updateAvatar(ProfileAvatarUpdateRequest $request): JsonResponse
    {
        $this->userService->updateAvatar($request->user(), $request->file('avatar'));

        return response()->json([
            'saved' => true,
            'message' => __('Profile picture updated.'),
            'avatar' => $request->user()->avatar,
        ], 200);
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $this->userService->destroy($user->id);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
