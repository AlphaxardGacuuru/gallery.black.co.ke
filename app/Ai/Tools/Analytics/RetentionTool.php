<?php

namespace App\Ai\Tools\Analytics;

use App\Enums\UserEventName;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class RetentionTool extends AnalyticsTool
{
    public function name(): string
    {
        return 'retention';
    }

    public function description(): Stringable|string
    {
        return 'Weekly retention: users grouped by the week (starting Monday) they first did an event, usually user.signed_up, '
            .'and how many of each group did anything in each following week. Week 0 is the week they joined.';
    }

    public function handle(Request $request): Stringable|string
    {
        $weeks = min(12, max(1, $request->integer('weeks', 6)));

        return $this->json($this->analytics->retention(
            $request->string('cohort_event', UserEventName::SIGNED_UP->value)->toString(),
            $weeks,
            $request->filled('from')
                ? $this->dates($request)[0]
                : CarbonImmutable::today()->subWeeks($weeks),
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cohort_event' => $schema->string()->enum($this->eventNames())->description('Defaults to user.signed_up.'),
            'weeks' => $schema->integer()->min(1)->max(12)->description('Weeks to follow each group for. Defaults to 6.'),
            'from' => $schema->string()->description('Earliest cohort week, YYYY-MM-DD. Defaults to "weeks" weeks ago.'),
        ];
    }
}
