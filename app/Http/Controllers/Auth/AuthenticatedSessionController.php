<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Services\AuthService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AuthenticatedSessionController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
        //
    }

    /*
     * Social Logins*/
    public function redirectToProvider(Request $request, string $website): RedirectResponse
    {
        // Google's redirect back to handleProviderCallback() carries no
        // custom params of ours, so a referral code picked up on the way in
        // is stashed in a short-lived cookie instead — it rides along
        // automatically since the callback is a same-site top-level GET.
        if ($request->filled('ref')) {
            Cookie::queue('referral_ref', $request->string('ref')->toString(), 10);
        }

        return Socialite::driver($website)->redirect();
    }

    /**
     * Obtain the user information from a social provider and issue a Sanctum token.
     */
    public function handleProviderCallback(Request $request, string $website): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($website)->stateless()->user();
        } catch (Exception $e) {
            return redirect('/login?error=' . urlencode('Authentication failed. Please try again.'));
        }

        $dbUser = $this->authService->findOrCreateFromSocialite($socialUser, $request->cookie('referral_ref'));

        Auth::login($dbUser);

        $token = $dbUser->createToken('web')->plainTextToken;

        return redirect('/socialite-callback?token=' . urlencode($token) . '&message=' . urlencode('Logged in') . '&provider=' . urlencode($website));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $user = $this->authService->findByCredentials($request->email, $request->password);

        if ($user->hasTwoFactorEnabled()) {
            $pendingToken = Str::uuid();
            
            Cache::put("2fa_pending:{$pendingToken}", $user->id, now()->addMinutes(5));

            return response([
                'message' => 'Two-factor authentication required',
                'two_factor' => true,
                'data' => $pendingToken,
            ], 200);
        }

        Auth::login($user);

        $token = $user
            ->createToken("$request->device_name")
            ->plainTextToken;

        return response([
            "message" => "Logged in",
            "data" => $token,
        ], 200);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {
        $user = auth("sanctum")->user();

        if (! $user) {
            return response(["message" => "No active authenticated user found"], 401);
        }

        // A session-authenticated (stateful SPA) request carries a
        // TransientToken rather than a stored token, so there's nothing to
        // revoke — only the web session below needs tearing down.
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response(["message" => "Logged Out"], 200);
    }
}
