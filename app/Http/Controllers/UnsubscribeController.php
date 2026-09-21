<?php

namespace App\Http\Controllers;

use App\Enums\EmailNotificationCategory;
use App\Models\User;
use Illuminate\View\View;

class UnsubscribeController extends Controller
{
    /**
     * Turn the given email category off for the user and confirm it — the
     * route this lives on is signed, so this link can't be forged or reused
     * for a different user/category than the email it was sent in.
     */
    public function show(User $user, EmailNotificationCategory $category): View
    {
        $settings = (array) ($user->settings ?? []);
        $settings[$category->settingsKey()] = false;
        $user->update(['settings' => $settings]);

        return view('unsubscribed', ['category' => $category]);
    }
}
