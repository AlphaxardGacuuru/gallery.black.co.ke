<?php

namespace App\Services;

use App\Enums\UserEventName;
use App\Models\Photo;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoLike;
use App\Models\PhotoPurchase;
use App\Models\PhotoSlotPurchase;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserEvent;
use App\Models\UserEventDailyTotal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The questions the analytics agent can ask of the data, each returning
 * plain numbers. Seed accounts and admins never reach user_events (see
 * EventRecorder), and the business numbers below leave seed accounts out
 * the same way.
 */
class UsageAnalyticsService
{
    /** Longest range one question can cover, to keep queries quick. */
    public const MAX_DAYS = 366;

    public function __construct(protected UserEventSummaryService $summaryService) {}

    /**
     * How often $event happened between $from and $to (inclusive dates),
     * grouped by day, source, page (route) or not at all. Finished days
     * come from the daily totals, today from the raw events. Users and
     * guests are distinct per row, so adding rows together can count
     * someone twice: activeUsers() gives exact distinct counts.
     */
    public function eventTotals(string $event, CarbonInterface $from, CarbonInterface $to, string $groupBy = 'day'): array
    {
        $today = today();

        $rows = UserEventDailyTotal::query()
            ->where('event', $event)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', min($to, $today->copy()->subDay())->toDateString())
            ->get()
            ->map(fn (UserEventDailyTotal $total) => [
                'date' => $total->date->toDateString(),
                'source' => $total->source,
                'route' => $total->route,
                'events' => $total->events,
                'users' => $total->users,
                'guests' => $total->guests,
            ])
            ->when($to->greaterThanOrEqualTo($today), fn (Collection $rows) => $rows->concat(
                $this->summaryService
                    ->rows($today)
                    ->where('event', $event)
            ));

        $key = match ($groupBy) {
            'source' => 'source',
            'route' => 'route',
            'none' => null,
            default => 'date',
        };

        return [
            'event' => $event,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'group_by' => $groupBy,
            'rows' => $rows
                ->groupBy(fn (array $row) => $key ? ($row[$key] ?: '(none)') : 'all')
                ->map(fn (Collection $group, string $value) => [
                    $key ?? 'total' => $value,
                    'events' => $group->sum('events'),
                    'users' => $group->sum('users'),
                    'guests' => $group->sum('guests'),
                ])
                ->sortKeys()
                ->values()
                ->all(),
        ];
    }

    /**
     * Distinct signed-in users and guest browsers who did anything (or
     * $event, when given) between $from and $to.
     */
    public function activeUsers(CarbonInterface $from, CarbonInterface $to, ?string $event = null): array
    {
        $counts = $this->between($from, $to)
            ->when($event, fn ($query) => $query->where('event', $event))
            ->toBase()
            ->selectRaw('COUNT(DISTINCT user_id) AS users, COUNT(DISTINCT CASE WHEN user_id IS NULL THEN anonymous_id END) AS guests')
            ->first();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'event' => $event ?? 'any',
            'users' => (int) $counts->users,
            'guests' => (int) $counts->guests,
            'note' => $from->lessThan(today()->subDays(UserEvent::PAGE_VIEW_RETENTION_DAYS))
                ? 'Page views older than '.UserEvent::PAGE_VIEW_RETENTION_DAYS.' days are deleted, so users and guests who only viewed pages then are missing.'
                : null,
        ];
    }

    /**
     * How many signed-in users did each of $steps in order between $from
     * and $to: each step counts only if it came after the user's previous
     * step.
     *
     * @param  array<int, string>  $steps
     */
    public function funnel(array $steps, CarbonInterface $from, CarbonInterface $to): array
    {
        $result = collect($steps)->reduce(function (array $carry, string $step) use ($from, $to) {
            $previous = $carry['reached'];

            $reached = $this->between($from, $to)
                ->where('event', $step)
                ->whereNotNull('user_id')
                ->when($previous !== null, fn ($query) => $query->whereIn('user_id', $previous->keys()))
                ->get(['user_id', 'created_at'])
                ->filter(fn (UserEvent $event) => $previous === null
                    || $event->created_at->greaterThanOrEqualTo($previous[$event->user_id]))
                ->groupBy('user_id')
                ->map(fn (Collection $events) => $events->min('created_at'));

            $first = $carry['steps'][0]['users'] ?? $reached->count();

            $carry['steps'][] = [
                'step' => $step,
                'users' => $reached->count(),
                'percent_of_first_step' => $first > 0 ? round($reached->count() / $first * 100, 1) : 0,
            ];
            $carry['reached'] = $reached;

            return $carry;
        }, ['reached' => null, 'steps' => []]);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'steps' => $result['steps'],
        ];
    }

    /**
     * Weekly retention: users grouped by the week (Monday start) they first
     * did $cohortEvent, and how many of them did anything in each of the
     * following $weeks weeks. Week 0 is the cohort week itself.
     */
    public function retention(string $cohortEvent, int $weeks, CarbonInterface $from): array
    {
        $start = CarbonImmutable::parse($from)->startOfWeek();
        $end = today()->endOfDay();

        $cohorts = UserEvent::query()
            ->where('event', $cohortEvent)
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $start)
            ->get(['user_id', 'created_at'])
            ->groupBy('user_id')
            ->map(fn (Collection $events) => CarbonImmutable::parse($events->min('created_at'))->startOfWeek())
            ->groupBy(fn (CarbonImmutable $week) => $week->toDateString(), preserveKeys: true);

        $activity = UserEvent::query()
            ->whereIn('user_id', $cohorts->flatMap(fn (Collection $members) => $members->keys()))
            ->where('created_at', '>=', $start)
            ->get(['user_id', 'created_at'])
            ->groupBy('user_id')
            ->map(fn (Collection $events) => $events
                ->map(fn (UserEvent $event) => $event->created_at->copy()->startOfWeek()->toDateString())
                ->unique());

        return [
            'cohort_event' => $cohortEvent,
            'cohorts' => $cohorts
                ->sortKeys()
                ->map(fn (Collection $members, string $week) => [
                    'week_of' => $week,
                    'users' => $members->count(),
                    'retained' => collect(range(0, $weeks))
                        ->map(fn (int $offset) => CarbonImmutable::parse($week)->addWeeks($offset))
                        ->filter(fn (CarbonImmutable $weekStart) => $weekStart->lessThanOrEqualTo($end))
                        ->map(function (CarbonImmutable $weekStart, int $offset) use ($members, $activity) {
                            $active = $members
                                ->keys()
                                ->filter(fn (string $userId) => ($activity[$userId] ?? collect())->contains($weekStart->toDateString()))
                                ->count();

                            return [
                                'week' => $offset,
                                'users' => $active,
                                'percent' => round($active / $members->count() * 100, 1),
                            ];
                        })
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Headline business numbers from the app's own tables, which go back
     * further than event tracking. Money is in KES.
     */
    public function businessStats(CarbonInterface $from, CarbonInterface $to): array
    {
        [$start, $end] = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $slotPurchases = PhotoSlotPurchase::query()
            ->where('status', PhotoSlotPurchase::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->get(['type', 'amount']);

        $photoSales = PhotoPurchase::query()
            ->where('status', PhotoPurchase::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end]);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'sign_ups' => User::query()->real()->whereBetween('created_at', [$start, $end])->count(),
            'photos_submitted' => Photo::query()->real()->whereBetween('created_at', [$start, $end])->count(),
            'likes_given_by_real_users' => PhotoLike::query()
                ->whereHas('user', fn ($query) => $query->real())
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            'prizes_awarded' => [
                'count' => PhotoCompetitionWinner::query()->whereBetween('created_at', [$start, $end])->count(),
                'amount' => (int) PhotoCompetitionWinner::query()->whereBetween('created_at', [$start, $end])->sum('prize_amount'),
            ],
            'photo_sales' => [
                'count' => (clone $photoSales)->count(),
                'revenue' => (int) (clone $photoSales)->sum('amount'),
            ],
            'extra_entry_purchases' => [
                'count' => $slotPurchases->where('type', PhotoSlotPurchase::TYPE_EXTRA_ENTRY)->count(),
                'revenue' => (int) $slotPurchases->where('type', PhotoSlotPurchase::TYPE_EXTRA_ENTRY)->sum('amount'),
            ],
            'extra_photo_purchases' => [
                'count' => $slotPurchases->where('type', PhotoSlotPurchase::TYPE_EXTRA_PHOTO)->count(),
                'revenue' => (int) $slotPurchases->where('type', PhotoSlotPurchase::TYPE_EXTRA_PHOTO)->sum('amount'),
            ],
            'referrals' => Referral::query()->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    /**
     * The date tracking began, so the agent knows how far back events go.
     */
    public function trackingStartedOn(): ?string
    {
        return UserEvent::query()
            ->min('created_at');
    }

    /**
     * Every event name the agent may ask about, with what it means.
     *
     * @return array<string, string>
     */
    public function eventCatalog(): array
    {
        return collect(UserEventName::cases())
            ->mapWithKeys(fn (UserEventName $event) => [$event->value => $event->description()])
            ->all();
    }

    private function between(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return UserEvent::query()
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->where('created_at', '<=', $to->copy()->endOfDay());
    }
}
