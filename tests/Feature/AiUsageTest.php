<?php

namespace Tests\Feature;

use App\Ai\Agents\SupportAgent;
use App\Models\AiUsage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AiUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use RuntimeException;
use Tests\TestCase;

class AiUsageTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'alphaxardgacuuru47@gmail.com';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        config()->set('ai.pricing.gemini-3.1-flash-lite', ['input' => 0.25, 'cached_input' => 0.025, 'output' => 1.50]);
    }

    public function test_an_assistant_reply_records_its_tokens_and_cost(): void
    {
        $user = User::factory()->create();

        SupportAgent::fake([$this->agentResponse(new Usage(promptTokens: 2000, completionTokens: 300, cacheReadInputTokens: 400, reasoningTokens: 100))]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/support-tickets', ['message' => 'How do I enter the challenge?'])
            ->assertCreated();

        $ticket = SupportTicket::query()
            ->sole();
        $usage = AiUsage::query()
            ->sole();

        $this->assertSame($user->id, $usage->user_id);
        $this->assertTrue($usage->subject->is($ticket));
        $this->assertSame(AiUsage::FEATURE_SUPPORT_REPLY, $usage->feature);
        $this->assertSame('gemini', $usage->provider);
        $this->assertSame('gemini-3.1-flash-lite', $usage->model);
        $this->assertNull($usage->attempt);
        $this->assertSame(2000, $usage->input_tokens);
        $this->assertSame(400, $usage->cached_input_tokens);
        $this->assertSame(300, $usage->output_tokens);
        $this->assertSame(100, $usage->reasoning_tokens);
        // 2000 x 0.25 + 400 x 0.025 + (300 + 100) x 1.50 = 500 + 10 + 600 micro-dollars.
        $this->assertSame(1110, $usage->cost_micros);
    }

    public function test_every_assistant_reply_is_recorded(): void
    {
        $user = User::factory()->create();

        SupportAgent::fake([
            ['reply' => 'First answer.', 'escalate' => false],
            ['reply' => 'Second answer.', 'escalate' => false],
        ]);

        $ticketId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/support-tickets', ['message' => 'How do I enter?'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/support-tickets/{$ticketId}/messages", ['message' => 'And how do I vote?'])
            ->assertSuccessful();

        $this->assertSame(2, AiUsage::query()
            ->where('subject_id', $ticketId)
            ->where('feature', AiUsage::FEATURE_SUPPORT_REPLY)
            ->count());
    }

    public function test_a_failed_ai_request_records_no_usage(): void
    {
        SupportAgent::fake(fn () => throw new RuntimeException('Gemini is down'));

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/support-tickets', ['message' => 'How do I enter the challenge?'])
            ->assertCreated();

        $this->assertSame(0, AiUsage::query()
            ->count());
    }

    public function test_usage_is_kept_when_the_ticket_is_deleted(): void
    {
        $user = User::factory()->create();

        SupportAgent::fake([$this->agentResponse(new Usage(1000, 100))]);

        $ticketId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/support-tickets', ['message' => 'How do I enter the challenge?'])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/support-tickets/{$ticketId}")
            ->assertSuccessful();

        $this->assertSame(1, AiUsage::query()
            ->count(), 'Deleting a ticket must not erase what it cost.');
    }

    public function test_models_without_a_price_are_recorded_at_zero_cost(): void
    {
        $this->assertSame(0, app(AiUsageService::class)
            ->costInMicros('some-unknown-model', 1000, 0, 1000));
        $this->assertSame(0, app(AiUsageService::class)
            ->costInMicros(null, 1000, 0, 1000));
    }

    public function test_admins_see_this_months_usage_totalled_per_user(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);
        $heavy = User::factory()->create(['name' => 'Heavy User']);
        $light = User::factory()->create(['name' => 'Light User']);

        $this->travelTo('2026-10-15 12:00');

        AiUsage::factory()
            ->for($heavy)
            ->count(2)
            ->create(['input_tokens' => 1000, 'cached_input_tokens' => 0, 'output_tokens' => 100, 'reasoning_tokens' => 0, 'cost_micros' => 400]);
        AiUsage::factory()
            ->for($heavy)
            ->create(['feature' => 'some_other_feature', 'input_tokens' => 2000, 'cached_input_tokens' => 0, 'output_tokens' => 50, 'reasoning_tokens' => 0, 'cost_micros' => 600]);
        AiUsage::factory()
            ->for($light)
            ->create(['input_tokens' => 500, 'cached_input_tokens' => 0, 'output_tokens' => 10, 'reasoning_tokens' => 0, 'cost_micros' => 100]);
        AiUsage::factory()
            ->for($light)
            ->create(['cost_micros' => 9999, 'created_at' => '2026-09-30 23:00']);

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.ai-usage.index'))
            ->assertOk()
            ->assertJsonPath('month', '2026-10')
            ->assertJsonPath('totals.costMicros', 1500)
            ->assertJsonPath('totals.requests', 4)
            ->assertJsonPath('totals.supportReplies', 3)
            ->assertJsonPath('users.0.name', 'Heavy User')
            ->assertJsonPath('users.0.requests', 3)
            ->assertJsonPath('users.0.supportReplies', 2)
            ->assertJsonPath('users.0.inputTokens', 4000)
            ->assertJsonPath('users.0.outputTokens', 250)
            ->assertJsonPath('users.0.costMicros', 1400)
            ->assertJsonPath('users.1.name', 'Light User')
            ->assertJsonPath('users.1.costMicros', 100);

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.ai-usage.index', ['month' => '2026-09']))
            ->assertOk()
            ->assertJsonPath('totals.costMicros', 9999);
    }

    public function test_the_month_must_be_valid(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN_EMAIL]);

        $this->actingAs($admin, 'sanctum')
            ->getJson(route('admin.ai-usage.index', ['month' => 'October']))
            ->assertJsonValidationErrors('month');
    }

    public function test_only_admins_can_see_ai_usage(): void
    {
        $user = User::factory()->create();

        $this->getJson(route('admin.ai-usage.index'))
            ->assertUnauthorized();

        $this->actingAs($user, 'sanctum')
            ->getJson(route('admin.ai-usage.index'))
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth')
            ->assertJsonPath('data.can_view_ai_usage', false);

        $this->actingAs(User::factory()->create(['email' => self::ADMIN_EMAIL]), 'sanctum')
            ->getJson('/api/auth')
            ->assertJsonPath('data.can_view_ai_usage', true);
    }

    private function agentResponse(Usage $usage): StructuredTextResponse
    {
        $reply = ['reply' => 'Go to the Compete page and tap Submit.', 'escalate' => false];

        return new StructuredTextResponse($reply, json_encode($reply), $usage, new Meta('gemini', 'gemini-3.1-flash-lite'));
    }
}
