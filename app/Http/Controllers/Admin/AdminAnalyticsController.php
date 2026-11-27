<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\AnalyticsAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAnalyticsController extends Controller
{
    public function __construct(protected AnalyticsAssistantService $service) {}

    /**
     * Ask the analytics agent a question. The chat lives in the browser,
     * which sends the conversation so far with the question last.
     */
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:20'],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        abort_unless(last($data['messages'])['role'] === 'user', 422, 'The last message must be the question.');

        return response()->json([
            'data' => $this->service->ask($request->user(), $data['messages']),
        ]);
    }
}
