<?php

namespace App\Http\Controllers\Chat;

use App\Events\ChatConversationRead;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatConversationResource;
use App\Http\Resources\ChatMessageResource;
use App\Http\Services\ChatConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChatConversationController extends Controller
{
    public function __construct(protected ChatConversationService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $conversations] = $this->service->index($request->boolean('archived'));

        return ChatConversationResource::collection($conversations)
            ->additional([
                'status' => $status,
                'message' => $message,
            ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->validate($request, ['userId' => 'required|exists:users,id']);

        [$saved, $message, $conversation] = $this->service->startWith($request->input('userId'));

        return response()->json([
            'saved' => $saved,
            'message' => $message,
            'data' => $conversation ? ChatConversationResource::make($conversation) : null,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        [$status, $message, $conversation, $messages] = $this->service->show(
            $id,
            (int) $request->input('page', 1)
        );

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => [
                'conversation' => ChatConversationResource::make($conversation),
                'messages' => ChatMessageResource::collection($messages->getCollection()->reverse()->values()),
                'meta' => [
                    'currentPage' => $messages->currentPage(),
                    'lastPage' => $messages->lastPage(),
                    'total' => $messages->total(),
                ],
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        [$status, $message, $conversation] = $this->service->markRead($id);

        ChatConversationRead::dispatchIf($status, $conversation, $request->user()->id);

        return response()->json([
            'status' => $status,
            'message' => $message,
        ]);
    }

    public function archive(string $id): JsonResponse
    {
        [$status, $message, $isArchived] = $this->service->toggleArchive($id);

        return response()->json([
            'status' => $status,
            'message' => $message,
            'isArchived' => $isArchived,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        [$status, $message] = $this->service->hideForMe($id);

        return response()->json([
            'status' => $status,
            'message' => $message,
        ]);
    }
}
