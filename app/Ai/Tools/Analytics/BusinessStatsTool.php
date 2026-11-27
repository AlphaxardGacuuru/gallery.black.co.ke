<?php

namespace App\Ai\Tools\Analytics;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class BusinessStatsTool extends AnalyticsTool
{
    public function name(): string
    {
        return 'business_stats';
    }

    public function description(): Stringable|string
    {
        return 'Headline numbers from the app\'s own records over a date range: sign-ups, photos submitted, likes, prizes awarded, photo sales, '
            .'extra entry and extra photo purchases (revenue in KES) and referrals. Goes back further than event tracking.';
    }

    public function handle(Request $request): Stringable|string
    {
        [$from, $to] = $this->dates($request);

        return $this->json($this->analytics->businessStats($from, $to));
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->dateSchema($schema);
    }
}
