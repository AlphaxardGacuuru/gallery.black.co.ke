<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use App\Http\Services\SupportTicketService;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSupportTicketController extends Controller
{
    public function __construct(protected SupportTicketService $service) {}

    /**
     * The support inbox, most recently active first, plus how many tickets
     * sit in each status (for the filter tabs).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(SupportTicketStatus::values())],
        ]);

        $tickets = $this->service
            ->search(SupportTicket::with(['user', 'latestMessage']), $request)
            ->orderByDesc('updated_at')
            ->paginate($request->integer('per_page', 20));

        $counts = SupportTicket::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'data' => SupportTicketResource::collection($tickets->getCollection()),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
                'counts' => collect(SupportTicketStatus::values())
                    ->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)]),
            ],
        ]);
    }

    public function show(SupportTicket $supportTicket): SupportTicketResource
    {
        return SupportTicketResource::make($supportTicket->load(['user', 'messages']));
    }

    /**
     * Reply as the support team. The customer gets a notification.
     */
    public function storeMessage(Request $request, SupportTicket $supportTicket): SupportTicketResource
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $this->service->addMessage(
            $supportTicket,
            SupportTicketMessage::AUTHOR_AGENT,
            $data['message'],
            $request->user(),
        );

        return SupportTicketResource::make($supportTicket->fresh(['user', 'messages']));
    }

    /**
     * Setting a ticket back to "open" hands it back to the AI assistant.
     */
    public function updateStatus(Request $request, SupportTicket $supportTicket): SupportTicketResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(SupportTicketStatus::values())],
        ]);

        $supportTicket->update(['status' => $data['status']]);

        return SupportTicketResource::make($supportTicket->fresh(['user', 'messages']));
    }
}
