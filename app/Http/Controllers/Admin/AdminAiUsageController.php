<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsage;
use App\Models\Setting;
use App\Services\AiUsageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAiUsageController extends Controller
{
    public function __construct(protected AiUsageService $service) {}

    /**
     * AI requests, tokens and cost per user for a month (this month by default).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            : CarbonImmutable::now()
                ->startOfMonth();

        $users = $this->service->monthlyTotals($month);

        return response()->json([
            'month' => $month->format('Y-m'),
            'agentsEnabled' => AiUsage::agentsEnabled(),
            'users' => $users,
            'totals' => [
                'requests' => $users->sum('requests'),
                'supportReplies' => $users->sum('supportReplies'),
                'inputTokens' => $users->sum('inputTokens'),
                'outputTokens' => $users->sum('outputTokens'),
                'costMicros' => $users->sum('costMicros'),
            ],
        ]);
    }

    /**
     * Switch AI agents on or off. While off, support tickets go straight
     * to a person and no AI requests are made.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'agentsEnabled' => ['required', 'boolean'],
        ]);

        Setting::query()
            ->updateOrCreate(['key' => AiUsage::SETTING_AGENTS_ENABLED], ['value' => $data['agentsEnabled']]);

        return response()->json(['data' => $data]);
    }
}
