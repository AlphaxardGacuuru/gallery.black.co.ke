<?php

namespace App\Console\Commands;

use App\Services\UserEventSummaryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:summarize-user-events {--days=3 : How many full days to count, ending yesterday}')]
#[Description('Counts user_events into user_event_daily_totals for the last few days.')]
class SummarizeUserEvents extends Command
{
    /**
     * Runs nightly (routes/console.php). Recounting a few days, not just
     * yesterday, picks up events the queue wrote late and guest events
     * linked to an account after the fact. Pass a bigger --days to fill in
     * history.
     */
    public function handle(UserEventSummaryService $summaryService): int
    {
        collect(range(1, max(1, (int) $this->option('days'))))
            ->map(fn (int $daysAgo) => today()->subDays($daysAgo))
            ->each(fn ($date) => $summaryService->summarize($date));

        return self::SUCCESS;
    }
}
