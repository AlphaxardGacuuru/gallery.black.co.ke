<?php

namespace App\Http\Controllers;

use App\Enums\SupportTicketStatus;
use App\Events\PhotoLikedEvent;
use App\Http\Services\PhotoService;
use App\Http\Services\UserService;
use App\Models\Photo;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * The background buttons on web push notifications. The service worker
 * posts to these through the signed links in each push (see
 * App\Notifications\Messages\WebPushMessage::signedAction()), so $user
 * comes from the signed URL, not a token.
 */
class PushActionController extends Controller
{
    public function __construct(
        protected PhotoService $photoService,
        protected UserService $userService,
    ) {
        //
    }

    /**
     * "Like back": like the photo of whoever liked $user's.
     */
    public function likePhoto(User $user, Photo $photo): JsonResponse
    {
        ['photo' => $photo, 'liked' => $liked] = $this->photoService->like($user, $photo->id);

        if ($liked) {
            broadcast(new PhotoLikedEvent($photo, $user));
        }

        return response()->json([
            'message' => "Liked {$photo->user?->name}'s photo",
        ]);
    }

    /**
     * "Mark as read" on a support reply, which clears the Feedback badge.
     */
    public function readSupportTicket(User $user, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($supportTicket->user_id === $user->id, 403);

        $supportTicket->markReadByCustomer();

        return response()->json([
            'message' => 'Marked as read',
        ]);
    }

    /**
     * "Resolve" on a customer's message, for the admin.
     */
    public function resolveSupportTicket(User $user, SupportTicket $supportTicket): JsonResponse
    {
        abort_unless($user->isAdmin(), 403);

        $supportTicket->update(['status' => SupportTicketStatus::RESOLVED->value]);

        return response()->json([
            'message' => "{$supportTicket->number} resolved",
        ]);
    }

    /**
     * "Verify" on a new signup, for the admin.
     */
    public function verifyUser(User $user, User $subject): JsonResponse
    {
        abort_unless($user->isAdmin(), 403);

        $this->userService->verify($subject);

        return response()->json([
            'message' => "{$subject->name} verified",
        ]);
    }
}
