<?php

namespace App\Ai\Tools\Analytics;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class FunnelTool extends AnalyticsTool
{
    public function name(): string
    {
        return 'funnel';
    }

    public function description(): Stringable|string
    {
        return 'A conversion funnel: how many signed-in users did each event in order over a date range, each step after their previous one, with the percentage of the first step that got there. '
            .'Guests\' page views count once they sign in on the same browser.';
    }

    public function handle(Request $request): Stringable|string
    {
        [$from, $to] = $this->dates($request);

        return $this->json($this->analytics->funnel(
            collect($request->array('steps'))
                ->map(fn ($step) => (string) $step)
                ->filter(fn (string $step) => in_array($step, $this->eventNames(), true))
                ->take(6)
                ->values()
                ->all(),
            $from,
            $to,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'steps' => $schema->array()
                ->items($schema->string()->enum($this->eventNames()))
                ->min(2)
                ->max(6)
                ->description('Event names in order, e.g. ["page.viewed", "photo.submitted"].')
                ->required(),
            ...$this->dateSchema($schema),
        ];
    }
}
