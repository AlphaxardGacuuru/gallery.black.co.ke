<?php

namespace App\Ai\Tools\Analytics;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class ActiveUsersTool extends AnalyticsTool
{
    public function name(): string
    {
        return 'active_users';
    }

    public function description(): Stringable|string
    {
        return 'Exact number of distinct signed-in users and guest browsers who did anything (or one event) over a date range. '
            .'Use for daily, weekly or monthly active users and for "how many people" questions.';
    }

    public function handle(Request $request): Stringable|string
    {
        [$from, $to] = $this->dates($request);

        return $this->json($this->analytics->activeUsers(
            $from,
            $to,
            $request->string('event')->toString() ?: null,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->dateSchema($schema),
            'event' => $schema->string()->enum($this->eventNames())->description('Only count people who did this event. Leave out for any activity.'),
        ];
    }
}
