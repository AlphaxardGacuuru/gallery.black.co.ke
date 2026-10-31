<?php

namespace App\Http\Controllers;

use App\Http\Services\NoticeService;
use App\Models\Notice;
use App\Models\NoticeEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoticeController extends Controller
{
    public function __construct(protected NoticeService $service) {}

    /**
     * The notice to show the signed-in user right now, if any.
     */
    public function next(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->next($request->user()),
        ]);
    }

    /**
     * Record that the user saw, clicked or dismissed a notice. "shown" is
     * sent when the modal actually opens, not when it's fetched, so
     * impressions (and the limits built on them) stay accurate.
     */
    public function storeEvent(Request $request, Notice $notice): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:16'],
            'action' => ['required', Rule::in(NoticeEvent::ACTIONS)],
        ]);

        $this->service->record($notice, $request->user(), $data['version'], $data['action']);

        return response()->json(['status' => true], 201);
    }
}
