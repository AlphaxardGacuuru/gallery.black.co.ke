<?php

namespace App\Http\Controllers\Admin;

use App\Events\PhoneNumberRequestedEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    /**
     * Email $user asking them to add the M-Pesa phone number that prizes
     * and payouts are sent to.
     */
    public function requestPhone(User $user): JsonResponse
    {
        if ($user->phone) {
            throw ValidationException::withMessages([
                'phone' => "{$user->name} already has a phone number.",
            ]);
        }

        PhoneNumberRequestedEvent::dispatch($user);

        return response()->json([
            'message' => "Asked {$user->name} to add their phone number.",
        ]);
    }
}
