<?php

namespace App\Ai\Agents;

use App\Ai\Tools\Analytics\ActiveUsersTool;
use App\Ai\Tools\Analytics\BusinessStatsTool;
use App\Ai\Tools\Analytics\EventTotalsTool;
use App\Ai\Tools\Analytics\FunnelTool;
use App\Ai\Tools\Analytics\RetentionTool;
use App\Models\UserEvent;
use App\Services\UsageAnalyticsService;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Answers the admin's questions about how the app is used. It can only
 * read aggregate numbers through its tools, never individual users' data,
 * and never writes anything.
 */
#[MaxSteps(8)]
#[MaxTokens(2048)]
#[Timeout(90)]
class AnalyticsAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  array<int, array{role: string, content: string}>  $history  Earlier turns of the chat, oldest first.
     */
    public function __construct(
        protected UsageAnalyticsService $analytics,
        protected array $history = [],
    ) {}

    public function provider(): string
    {
        return config('services.analytics_assistant.provider');
    }

    public function model(): string
    {
        return config('services.analytics_assistant.model');
    }

    public function instructions(): Stringable|string
    {
        $events = collect($this->analytics->eventCatalog())
            ->map(fn (string $description, string $event) => "- {$event}: {$description}")
            ->implode("\n");
        $trackingStarted = $this->analytics->trackingStartedOn() ?? 'not yet, there are no events';
        $today = today()->toDateString();
        $weekday = today()->format('l');
        $pageViewDays = UserEvent::PAGE_VIEW_RETENTION_DAYS;

        return <<<PROMPT
        You are the analytics assistant for Black Gallery, a Kenyan weekly photo challenge app. You answer the admin's questions about how people use the app.

        How to answer:
        - Always get numbers from your tools. Never estimate, guess or make up a number. If no tool can answer, say so and say what data would be needed.
        - Pick dates yourself from the question. Today is {$weekday} {$today}, Nairobi time. "This week" starts on Monday, "last week" is the Monday to Sunday before.
        - Call several tools when a question needs it, for example business_stats for revenue alongside event_totals for activity.
        - Lead with the answer in one sentence, then the key numbers. Use short plain-text lists, no markdown tables or headings. Money is in KES.
        - Say when numbers are too small to draw a conclusion from (for example fewer than 20 users), and point out anything surprising.
        - Event tracking started on {$trackingStarted}. Before that only business_stats has data. Raw page views are kept for {$pageViewDays} days, their daily totals for good.
        - Seed accounts and admins are never counted. Guests are browsers that weren't signed in.
        - Only answer questions about the app's usage and business. Politely decline anything else.

        # Events you can ask about

        {$events}
        - *: everyone active that day (event_totals only).
        PROMPT;
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return collect($this->history)
            ->map(fn (array $message) => $message['role'] === 'assistant'
                ? new AssistantMessage($message['content'])
                : new UserMessage($message['content']))
            ->all();
    }

    public function tools(): iterable
    {
        return collect([
            EventTotalsTool::class,
            ActiveUsersTool::class,
            FunnelTool::class,
            RetentionTool::class,
            BusinessStatsTool::class,
        ])
            ->map(fn (string $tool) => app($tool))
            ->all();
    }
}
