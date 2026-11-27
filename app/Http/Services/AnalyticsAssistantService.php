<?php

namespace App\Http\Services;

use App\Ai\Agents\AnalyticsAgent;
use App\Models\AiUsage;
use App\Models\User;
use App\Services\AiUsageService;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\Data\ToolCall;

class AnalyticsAssistantService extends Service
{
    public function __construct(protected AiUsageService $aiUsageService)
    {
        parent::__construct();
    }

    /**
     * Answer the last message in $messages (the admin's question) with the
     * earlier ones as context, and say which tools the answer came from.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{answer: string, toolCalls: array<int, array{name: string, arguments: array}>}
     */
    public function ask(User $admin, array $messages): array
    {
        if (! AiUsage::agentsEnabled()) {
            throw ValidationException::withMessages([
                'messages' => 'AI agents are switched off. Turn them on from the AI usage page.',
            ]);
        }

        $question = array_pop($messages);

        $response = app(AnalyticsAgent::class, ['history' => $messages])
            ->prompt($question['content']);

        $this->aiUsageService->record($admin->id, AiUsage::FEATURE_ANALYTICS, $response);

        return [
            'answer' => $response->text,
            'toolCalls' => $response->toolCalls
                ->map(fn (ToolCall $call) => [
                    'name' => $call->name,
                    'arguments' => $call->arguments,
                ])
                ->values()
                ->all(),
        ];
    }
}
