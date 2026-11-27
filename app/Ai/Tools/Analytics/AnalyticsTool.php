<?php

namespace App\Ai\Tools\Analytics;

use App\Enums\UserEventName;
use App\Models\UserEventDailyTotal;
use App\Services\UsageAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Shared by the analytics agent's tools: reading a date range and
 * returning results as JSON. Each tool only reads aggregate numbers.
 */
abstract class AnalyticsTool implements Tool
{
    public function __construct(protected UsageAnalyticsService $analytics) {}

    /**
     * The from and to dates (inclusive, Nairobi time), defaulting to the
     * last 7 days and capped at UsageAnalyticsService::MAX_DAYS.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    protected function dates(Request $request): array
    {
        $to = $this->date($request->string('to')->toString()) ?? CarbonImmutable::today();
        $from = $this->date($request->string('from')->toString()) ?? $to->subDays(6);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [max($from, $to->subDays(UsageAnalyticsService::MAX_DAYS - 1)), $to];
    }

    protected function json(array $result): string
    {
        return json_encode($result, JSON_PRETTY_PRINT);
    }

    protected function dateSchema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('First day, YYYY-MM-DD. Defaults to 6 days before "to".'),
            'to' => $schema->string()->description('Last day, YYYY-MM-DD, inclusive. Defaults to today.'),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function eventNames(bool $withEveryone = false): array
    {
        return collect(UserEventName::cases())
            ->map(fn (UserEventName $event) => $event->value)
            ->when($withEveryone, fn ($names) => $names->prepend(UserEventDailyTotal::ALL_EVENTS))
            ->all();
    }

    private function date(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
