<?php

namespace App\Http\Controllers;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Resources\SupportTicketResource;
use App\Http\Services\SupportTicketService;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in user's own support conversations. The admin side lives in
 * Admin\AdminSupportTicketController.
 */
class SupportTicketController extends Controller
{
    public function __construct(protected SupportTicketService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SupportTicketResource::collection($this->service->index($request->user()));
    }

    /**
     * Start a conversation. The AI assistant replies in the background;
     * the client polls show() until the reply lands.
     */
    public function store(Request $request): SupportTicketResource
    {
        $this->validate($request, [
            "message" => "required|string|max:2000",
            "category" => "nullable|in:".implode(',', SupportTicketCategory::values()),
            "subject" => "nullable|string|max:255",
            "priority" => "nullable|in:".implode(',', SupportTicketPriority::values()),
            "temporaryUploadIds" => "nullable|array",
            "temporaryUploadIds.*" => "integer|exists:temporary_uploads,id",
        ]);

        $supportTicket = $this->service->store($request, $request->user());

        // The assistant may already have replied (sync queue), so reload the
        // conversation without losing wasRecentlyCreated (the 201).
        return SupportTicketResource::make($supportTicket->load('messages'));
    }

    public function show(Request $request, string $id): SupportTicketResource
    {
        return SupportTicketResource::make($this->service->findForUser($request->user(), $id));
    }

    public function storeMessage(Request $request, string $id): SupportTicketResource
    {
        $this->validate($request, [
            "message" => "required|string|max:2000",
        ]);

        $supportTicket = $this->service->findForUser($request->user(), $id);

        $this->service->addMessage(
            $supportTicket,
            SupportTicketMessage::AUTHOR_USER,
            $request->input('message'),
            $request->user(),
        );

        return SupportTicketResource::make($supportTicket->fresh(['user', 'messages']));
    }

    /**
     * The customer asked to talk to a person.
     */
    public function escalate(Request $request, string $id): SupportTicketResource
    {
        $supportTicket = $this->service->findForUser($request->user(), $id);

        $this->service->escalate($supportTicket);

        return SupportTicketResource::make($supportTicket->fresh(['user', 'messages']));
    }

    public function close(Request $request, string $id): SupportTicketResource
    {
        $supportTicket = $this->service->findForUser($request->user(), $id);

        $supportTicket->update(['status' => SupportTicketStatus::RESOLVED->value]);

        return SupportTicketResource::make($supportTicket->fresh(['user', 'messages']));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = $this->service->destroy($this->service->findForUser($request->user(), $id));

        return response()->json([
            "deleted" => $deleted,
            "message" => "Support Ticket Deleted Successfully",
        ]);
    }
}
