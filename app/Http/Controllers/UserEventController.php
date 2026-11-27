<?php

namespace App\Http\Controllers;

use App\Enums\UserEventName;
use App\Models\Photo;
use App\Services\EventRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class UserEventController extends Controller
{
    public function __construct(protected EventRecorder $eventRecorder) {}

    /**
     * Record an event from the browser: a page view or a share. Guests
     * send just their anonymous id, signed-in users both, which links the
     * guest's earlier events to the account.
     */
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'event' => ['required', Rule::enum(UserEventName::class)->only(UserEventName::clientEvents())],
            'anonymousId' => ['required', 'uuid'],
            'path' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:50'],
            'photoId' => ['nullable', 'uuid'],
            'properties' => ['nullable', 'array', 'max:10'],
            'properties.*' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $this->eventRecorder->record(
            UserEventName::from($data['event']),
            $user,
            photo: isset($data['photoId']) ? Photo::query()->find($data['photoId']) : null,
            source: $data['source'] ?? null,
            properties: $data['properties'] ?? [],
            anonymousId: $data['anonymousId'],
            path: $data['path'] ?? null,
        );

        if ($user) {
            $this->eventRecorder->linkAnonymous($user, $data['anonymousId']);
        }

        return response()->noContent();
    }
}
