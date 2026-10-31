<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\NoticeVariables;
use App\Models\Notice;
use App\Models\NoticeEvent;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminNoticeController extends Controller
{
    public function __construct(protected NoticeVariables $variables)
    {
        //
    }

    /**
     * Every notice with its stats, the global limits, and what the editor
     * needs (variables with current values, audiences, icons). The event
     * log is paginated separately via events().
     */
    public function index(Request $request): JsonResponse
    {
        $counts = NoticeEvent::query()
            ->selectRaw('notice_id, action, count(*) as total')
            ->groupBy('notice_id', 'action')
            ->get()
            ->groupBy('notice_id');

        $viewers = NoticeEvent::query()
            ->where('action', NoticeEvent::ACTION_SHOWN)
            ->selectRaw('notice_id, count(distinct user_id) as total')
            ->groupBy('notice_id')
            ->pluck('total', 'notice_id');

        $notices = Notice::query()
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->latest()
            ->get()
            ->map(function (Notice $notice) use ($counts, $viewers) {
                $byAction = ($counts[$notice->id] ?? collect())->pluck('total', 'action');

                return [
                    ...$this->present($notice),
                    'stats' => [
                        'shown' => (int) ($byAction[NoticeEvent::ACTION_SHOWN] ?? 0),
                        'uniqueViewers' => (int) ($viewers[$notice->id] ?? 0),
                        'primaryClicks' => (int) ($byAction[NoticeEvent::ACTION_PRIMARY_CLICK] ?? 0),
                        'secondaryClicks' => (int) ($byAction[NoticeEvent::ACTION_SECONDARY_CLICK] ?? 0),
                        'dismissed' => (int) ($byAction[NoticeEvent::ACTION_DISMISSED] ?? 0),
                    ],
                ];
            });

        return response()->json([
            'data' => [
                'notices' => $notices,
                'limits' => Notice::limits(),
                'variables' => $this->variables->catalogue($request->user()),
                'audiences' => Notice::AUDIENCES,
                'icons' => Notice::ICONS,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $notice = Notice::query()->create($this->validated($request));

        return response()->json(['data' => $this->present($notice)], 201);
    }

    public function update(Request $request, Notice $notice): JsonResponse
    {
        $notice->update($this->validated($request, $notice));

        return response()->json(['data' => $this->present($notice)]);
    }

    public function destroy(Notice $notice): JsonResponse
    {
        $notice->delete();

        return response()->json(['status' => true]);
    }

    /**
     * Who did what with which notice, most recent first. Optionally
     * narrowed to one notice and/or one action.
     */
    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'notice_id' => ['nullable', 'string'],
            'action' => ['nullable', Rule::in(NoticeEvent::ACTIONS)],
        ]);

        $events = NoticeEvent::query()
            ->with(['notice', 'user'])
            ->when($request->query('notice_id'), fn($q, $id) => $q->where('notice_id', $id))
            ->when($request->query('action'), fn($q, $action) => $q->where('action', $action))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $events->getCollection()->map(fn(NoticeEvent $event) => [
                'id' => $event->id,
                'noticeKey' => $event->notice?->key,
                'userName' => $event->user?->name,
                'userPhone' => $event->user?->phone,
                'version' => $event->version,
                'action' => $event->action,
                'createdAt' => $event->created_at,
            ]),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * The global caps every notice shares: how many a user may see per
     * rolling 24 hours, and the minimum gap between any two.
     */
    public function updateLimits(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dailyLimit' => 'required|integer|min:0|max:24',
            'minGapHours' => 'required|integer|min:0|max:168',
        ]);

        Setting::query()->updateOrCreate(['key' => 'notices_daily_limit'], ['value' => $data['dailyLimit']]);
        Setting::query()->updateOrCreate(['key' => 'notices_min_gap_hours'], ['value' => $data['minGapHours']]);

        return response()->json(['data' => $data]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Notice $notice = null): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('notices', 'key')->ignore($notice?->id)],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'icon' => ['required', Rule::in(Notice::ICONS)],
            'primaryLabel' => ['required', 'string', 'max:40'],
            // Internal paths ("/settings/referrals") or full https links.
            'primaryUrl' => ['nullable', 'string', 'max:255', 'regex:#^(/[^/]|/$|https://)#'],
            'secondaryLabel' => ['nullable', 'string', 'max:40'],
            'audience' => ['required', Rule::in(Notice::AUDIENCES)],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'cooldownHours' => ['required', 'integer', 'min:0', 'max:' . 24 * 90],
            'maxImpressions' => ['nullable', 'integer', 'min:1'],
            'stopAfterClick' => ['required', 'boolean'],
            'isActive' => ['required', 'boolean'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
        ]);

        $unknown = [];

        foreach (['title', 'body', 'primaryLabel', 'secondaryLabel'] as $field) {
            foreach ($this->variables->unknownIn((string) ($data[$field] ?? '')) as $name) {
                $unknown[$field][] = "{{$name}} isn't a known variable.";
            }
        }

        if ($unknown !== []) {
            throw ValidationException::withMessages($unknown);
        }

        return [
            'key' => $data['key'],
            'title' => $data['title'],
            'body' => $data['body'],
            'icon' => $data['icon'],
            'primary_label' => $data['primaryLabel'],
            'primary_url' => $data['primaryUrl'] ?? null,
            'secondary_label' => $data['secondaryLabel'] ?? null,
            'audience' => $data['audience'],
            'priority' => $data['priority'],
            'cooldown_hours' => $data['cooldownHours'],
            'max_impressions' => $data['maxImpressions'] ?? null,
            'stop_after_click' => $data['stopAfterClick'],
            'is_active' => $data['isActive'],
            'starts_at' => $data['startsAt'] ?? null,
            'ends_at' => $data['endsAt'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Notice $notice): array
    {
        return [
            'id' => $notice->id,
            'key' => $notice->key,
            'title' => $notice->title,
            'body' => $notice->body,
            'icon' => $notice->icon,
            'primaryLabel' => $notice->primary_label,
            'primaryUrl' => $notice->primary_url,
            'secondaryLabel' => $notice->secondary_label,
            'audience' => $notice->audience,
            'priority' => $notice->priority,
            'cooldownHours' => $notice->cooldown_hours,
            'maxImpressions' => $notice->max_impressions,
            'stopAfterClick' => $notice->stop_after_click,
            'isActive' => $notice->is_active,
            'startsAt' => $notice->starts_at,
            'endsAt' => $notice->ends_at,
        ];
    }
}
