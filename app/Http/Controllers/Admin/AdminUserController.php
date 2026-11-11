<?php

namespace App\Http\Controllers\Admin;

use App\Events\PhoneNumberRequestedEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Services\UserService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function __construct(protected UserService $userService)
    {
        //
    }

    /**
     * Flag or unflag $user. Flagging notifies them on every channel, with
     * how to dispute it. The reason is an admin-only note.
     */
    public function flag(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'flagged' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['flagged'] && ! $user->isFlagged()) {
            $this->userService->flag($user, $data['reason'] ?? null);
        }

        if (! $data['flagged'] && $user->isFlagged()) {
            $this->userService->unflag($user);
        }

        return response()->json([
            'data' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Permanently delete $user and everything they posted.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->userService->deleteAccount($user);

        return response()->json([
            'message' => "{$user->name} deleted",
        ]);
    }

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
