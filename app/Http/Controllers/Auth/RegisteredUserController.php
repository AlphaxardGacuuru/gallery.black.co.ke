<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserCreatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
        //
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): Response
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $this->authService->register(
            $request->name,
            $request->email,
            $request->password,
            $request->input('referrerId'),
        );

        Auth::login($user);

        $token = $user
            ->createToken($request->device_name)
            ->plainTextToken;

        UserCreatedEvent::dispatch($user);

        return response([
            "status" => "success",
            "message" => "Registered Successfully",
            "data" => $token,
        ], 200);
    }
}
