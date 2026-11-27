<?php

namespace App\Services;

use App\Models\UserEvent;
use App\Models\UserEventDailyTotal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserEventSummaryService
{
    private const COUNTS = 'COUNT(*) AS events, COUNT(DISTINCT user_id) AS users, '
        ."COUNT(DISTINCT CASE WHEN user_id IS NULL THEN anonymous_id END) AS guests";

    private const SOURCE = "COALESCE(source, '')";

    private const ROUTE = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(properties, '$.route')), '')";

    /**
     * Replace $date's daily totals with fresh counts from user_events, so
     * running it again for the same day is safe.
     */
    public function summarize(CarbonInterface $date): void
    {
        $rows = $this->rows($date)
            ->map(fn (array $row) => [
                ...$row,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::transaction(function () use ($date, $rows) {
            UserEventDailyTotal::query()
                ->whereDate('date', $date->toDateString())
                ->delete();

            UserEventDailyTotal::query()
                ->insert($rows->all());
        });
    }

    /**
     * $date's counts straight from user_events, one row per event, source
     * and page, plus the "*" row for everyone active. Also used for today,
     * which isn't summarized until it's over.
     *
     * @return Collection<int, array{date: string, event: string, source: string, route: string, events: int, users: int, guests: int}>
     */
    public function rows(CarbonInterface $date): Collection
    {
        $day = UserEvent::query()
            ->toBase()
            ->where('created_at', '>=', $date->copy()->startOfDay())
            ->where('created_at', '<', $date->copy()->addDay()->startOfDay());

        return (clone $day)
            ->selectRaw('event, '.self::SOURCE.' AS source, '.self::ROUTE.' AS route, '.self::COUNTS)
            ->groupByRaw('event, '.self::SOURCE.', '.self::ROUTE)
            ->get()
            ->push((clone $day)
                ->selectRaw("'".UserEventDailyTotal::ALL_EVENTS."' AS event, '' AS source, '' AS route, ".self::COUNTS)
                ->first())
            ->reject(fn (object $row) => (int) $row->events === 0)
            ->map(fn (object $row) => [
                'date' => $date->toDateString(),
                'event' => $row->event,
                'source' => $row->source,
                'route' => $row->route,
                'events' => (int) $row->events,
                'users' => (int) $row->users,
                'guests' => (int) $row->guests,
            ])
            ->values();
    }
}
