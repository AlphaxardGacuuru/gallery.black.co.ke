<?php

namespace App\Ai\Tools\Analytics;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class EventTotalsTool extends AnalyticsTool
{
    public function name(): string
    {
        return 'event_totals';
    }

    public function description(): Stringable|string
    {
        return 'How many times an event happened over a date range, and by how many users and guests, grouped by day, source, page (route) or as one total. '
            .'Use event "*" for everyone active each day. Users and guests are distinct within a row only, so adding rows can count someone twice; use active_users for exact distinct counts.';
    }

    public function handle(Request $request): Stringable|string
    {
        [$from, $to] = $this->dates($request);

        return $this->json($this->analytics->eventTotals(
            $request->string('event')->toString(),
            $from,
            $to,
            $request->string('group_by', 'day')->toString(),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->string()->enum($this->eventNames(withEveryone: true))->required(),
            ...$this->dateSchema($schema),
            'group_by' => $schema->string()->enum(['day', 'source', 'route', 'none'])->description('Defaults to day.'),
        ];
    }
}
