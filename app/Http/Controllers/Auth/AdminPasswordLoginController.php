<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Password sign-in for the password login admin account. Everyone else
 * signs in with Google. The page and the form post both need a signed link
 * that only a signed-in admin can get, so the form can't be reached, or
 * brute forced, from outside the admin portal.
 */
class AdminPasswordLoginController extends Controller
{
    public const LINK_MINUTES = 10;

    public function __construct(protected AuthService $authService)
    {
        //
    }

    /**
     * A short lived signed link to the password login page. Relative, so it
     * still validates behind the production proxy.
     */
    public function link(): Response
    {
        $url = URL::temporarySignedRoute(
            'admin-password-login',
            now()->addMinutes(self::LINK_MINUTES),
            absolute: false,
        );

        return response(['data' => $url]);
    }

    public function store(Request $request): Response
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->authService->findPasswordLoginAdmin($request->email, $request->password);

        if ($user->hasTwoFactorEnabled()) {
            $pendingToken = Str::uuid();

            Cache::put("2fa_pending:{$pendingToken}", $user->id, now()->addMinutes(5));

            return response([
                'message' => 'Two-factor authentication required',
                'two_factor' => true,
                'data' => $pendingToken,
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();

        $token = $user->createToken('web')
            ->plainTextToken;

        return response([
            'message' => 'Logged in',
            'data' => $token,
        ]);
    }
}
