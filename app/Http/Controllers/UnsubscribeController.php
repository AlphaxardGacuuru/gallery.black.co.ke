<?php

namespace App\Http\Controllers;

use App\Enums\EmailNotificationCategory;
use App\Http\Services\UserService;
use App\Models\User;
use Illuminate\View\View;

class UnsubscribeController extends Controller
{
    public function __construct(protected UserService $userService)
    {
        //
    }

    /**
     * Turn the given email category off for the user and confirm it — the
     * route this lives on is signed, so this link can't be forged or reused
     * for a different user/category than the email it was sent in.
     */
    public function show(User $user, EmailNotificationCategory $category): View
    {
        $this->userService->unsubscribe($user, $category);

        return view('unsubscribed', ['category' => $category]);
    }
}
