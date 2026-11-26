<?php

namespace Tests\Feature;

use App\Ai\Agents\SupportAgent;
use App\Enums\SupportTicketStatus;
use App\Http\Services\SupportTicketService;
use App\Jobs\AnswerSupportTicketJob;
use App\Models\Photo;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\AdminSupportTicketEscalatedNotification;
use App\Notifications\AdminSupportTicketMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use NotificationChannels\WebPush\WebPushChannel;
use RuntimeException;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function start(User $user, string $message = 'How do I enter the challenge?'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/api/support-tickets', ['message' => $message]);
    }

    public function test_starting_a_conversation_gets_an_ai_reply(): void
    {
        SupportAgent::fake([['reply' => 'Go to the Compete page and tap Submit.', 'escalate' => false]]);

        $response = $this->start(User::factory()->create())->assertCreated();

        $ticketId = $response->json('data.id');
        $ticket = SupportTicket::with('messages')->findOrFail($ticketId);

        $this->assertSame(SupportTicketStatus::OPEN->value, $ticket->status);
        $this->assertSame('How do I enter the challenge?', $ticket->subject);
        $this->assertSame(
            [
                [SupportTicketMessage::AUTHOR_USER, 'How do I enter the challenge?'],
                [SupportTicketMessage::AUTHOR_ASSISTANT, 'Go to the Compete page and tap Submit.'],
            ],
            $ticket->messages->map(fn ($message) => [$message->author, $message->body])->all(),
        );

        SupportAgent::assertPrompted('How do I enter the challenge?');
    }

    public function test_the_assistant_can_hand_the_ticket_to_a_person(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        SupportAgent::fake([['reply' => "I've passed this to the team.", 'escalate' => true]]);

        $ticketId = $this->start(User::factory()->create(), 'I paid but nothing unlocked')->json('data.id');

        $this->assertSame(SupportTicketStatus::NEEDS_AGENT->value, SupportTicket::find($ticketId)->status);
        Notification::assertSentTo($admin, AdminSupportTicketEscalatedNotification::class);
    }

    public function test_asking_for_a_person_notifies_the_admin(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $customer = User::factory()->create();
        SupportAgent::fake([['reply' => 'Try entering from the challenge page.', 'escalate' => false]]);

        $ticketId = $this->start($customer)->json('data.id');

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/support-tickets/{$ticketId}/escalate")
            ->assertOk()
            ->assertJsonPath('data.status', SupportTicketStatus::NEEDS_AGENT->value);

        Notification::assertSentTo(
            $admin,
            AdminSupportTicketEscalatedNotification::class,
            fn ($notification, array $channels) => $channels === ['mail', 'database', WebPushChannel::class],
        );
    }

    public function test_customer_messages_notify_the_admin_once_a_person_has_the_ticket(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $customer = User::factory()->create();
        SupportAgent::fake([['reply' => 'Hi!', 'escalate' => false]]);

        $ticketId = $this->start($customer)->json('data.id');

        Notification::assertNotSentTo($admin, AdminSupportTicketMessageNotification::class);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/support-tickets/{$ticketId}/escalate")
            ->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/support-tickets/{$ticketId}/messages", ['message' => 'Hello?'])
            ->assertOk();

        Notification::assertSentTo(
            $admin,
            AdminSupportTicketMessageNotification::class,
            fn ($notification, array $channels) => $channels === ['database', WebPushChannel::class],
        );
    }

    public function test_a_reply_shows_as_unread_until_the_customer_opens_the_ticket(): void
    {
        SupportAgent::fake([['reply' => 'Hi!', 'escalate' => false]]);
        $customer = User::factory()->create();
        $ticketId = $this->start($customer)->json('data.id');
        $ticket = SupportTicket::findOrFail($ticketId);

        $this->travel(1)->minute();
        app(SupportTicketService::class)->addMessage($ticket, SupportTicketMessage::AUTHOR_AGENT, 'We are on it.');

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/support-tickets/pending')
            ->assertOk()
            ->assertExactJson(['data' => ['unread' => 1, 'waiting' => null]]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/support-tickets')
            ->assertJsonPath('data.0.unread', true);

        $this->travel(1)->minute();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/support-tickets/{$ticketId}")
            ->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/support-tickets/pending')
            ->assertJsonPath('data.unread', 0);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/support-tickets')
            ->assertJsonPath('data.0.unread', false);
    }

    public function test_reading_a_ticket_does_not_bump_it_up_the_admin_inbox(): void
    {
        SupportAgent::fake([['reply' => 'Hi!', 'escalate' => false]]);
        $customer = User::factory()->create();
        $ticketId = $this->start($customer)->json('data.id');
        $updatedAt = SupportTicket::findOrFail($ticketId)->getRawOriginal('updated_at');

        $this->travel(1)->hour();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/support-tickets/{$ticketId}")
            ->assertOk();

        $this->assertSame($updatedAt, SupportTicket::findOrFail($ticketId)->getRawOriginal('updated_at'));
    }

    public function test_the_customer_sees_when_the_team_has_read_their_message(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $customer = User::factory()->create();
        SupportAgent::fake([['reply' => 'Hi!', 'escalate' => true]]);
        $ticketId = $this->start($customer)->json('data.id');
        $updatedAt = SupportTicket::findOrFail($ticketId)->getRawOriginal('updated_at');

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/support-tickets/{$ticketId}")
            ->assertJsonPath('data.agentReadAt', null);

        $this->travel(1)->hour();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/support-tickets/{$ticketId}")
            ->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/support-tickets/{$ticketId}")
            ->assertJsonPath('data.agentReadAt', now()->toIso8601String());

        // Reading doesn't bump the ticket up the inbox.
        $this->assertSame($updatedAt, SupportTicket::findOrFail($ticketId)->getRawOriginal('updated_at'));
    }

    public function test_the_admin_sees_when_the_customer_has_read_a_reply(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $customer = User::factory()->create();
        SupportAgent::fake([['reply' => 'Hi!', 'escalate' => true]]);
        $ticketId = $this->start($customer)->json('data.id');

        $this->travel(1)->minute();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/support-tickets/{$ticketId}/messages", ['message' => 'We are on it.'])
            ->assertOk()
            ->assertJsonPath('data.agentReadAt', now()->toIso8601String())
            ->assertJsonPath('data.userReadAt', fn ($readAt) => $readAt < now()->toIso8601String());

        $this->travel(1)->minute();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/support-tickets/{$ticketId}")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/support-tickets/{$ticketId}")
            ->assertJsonPath('data.userReadAt', now()->toIso8601String());
    }

    public function test_the_admin_badge_counts_tickets_waiting_on_a_person(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $customer = User::factory()->create();
        SupportAgent::fake([
            ['reply' => 'Hi!', 'escalate' => false],
            ['reply' => 'Hi!', 'escalate' => false],
            ['reply' => 'Hi!', 'escalate' => false],
        ]);

        // Handed over and not answered yet.
        $handedOver = $this->start($customer)->json('data.id');
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/support-tickets/{$handedOver}/escalate")
            ->assertOk();

        // Answered by a person, then the customer wrote again.
        $followedUp = SupportTicket::findOrFail($this->start($customer)->json('data.id'));
        app(SupportTicketService::class)->addMessage($followedUp, SupportTicketMessage::AUTHOR_AGENT, 'Done.');
        $this->travel(1)->minute();
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/support-tickets/{$followedUp->id}/messages", ['message' => 'Thanks, one more thing'])
            ->assertOk();

        // Answered by a person, customer hasn't written since.
        $answered = SupportTicket::findOrFail($this->start($customer)->json('data.id'));
        app(SupportTicketService::class)->addMessage($answered, SupportTicketMessage::AUTHOR_AGENT, 'Done.');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/support-tickets/pending')
            ->assertOk()
            ->assertJsonPath('data.waiting', 2);
    }

    public function test_an_ai_failure_hands_the_ticket_to_a_person_after_every_attempt(): void
    {
        Sleep::fake();
        $attempts = 0;
        SupportAgent::fake(function () use (&$attempts) {
            $attempts++;

            throw new RuntimeException('Gemini is down');
        });

        $ticketId = $this->start(User::factory()->create())->json('data.id');
        $ticket = SupportTicket::find($ticketId);

        $this->assertSame(SupportTicketService::ASSISTANT_ATTEMPTS, $attempts);
        $this->assertSame(SupportTicketStatus::NEEDS_AGENT->value, $ticket->status);
        $this->assertSame(SupportTicketService::HANDOFF_MESSAGE, $ticket->latestMessage->body);
    }

    public function test_a_passing_ai_failure_is_retried_instead_of_handed_over(): void
    {
        Sleep::fake();
        $attempts = 0;
        SupportAgent::fake(function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new RuntimeException('503 The model is overloaded');
            }

            return ['reply' => 'Tap Submit a photo on the Compete page.', 'escalate' => false];
        });

        $ticketId = $this->start(User::factory()->create())->json('data.id');
        $ticket = SupportTicket::find($ticketId);

        $this->assertSame(SupportTicketStatus::OPEN->value, $ticket->status);
        $this->assertSame('Tap Submit a photo on the Compete page.', $ticket->latestMessage->body);
    }

    public function test_an_empty_ai_reply_is_logged_before_handing_over(): void
    {
        Log::spy();
        SupportAgent::fake([['reply' => '', 'escalate' => false]]);

        $ticketId = $this->start(User::factory()->create())->json('data.id');

        $this->assertSame(SupportTicketStatus::NEEDS_AGENT->value, SupportTicket::find($ticketId)->status);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'empty reply'))
            ->once();
    }

    public function test_every_ai_attempt_fits_in_the_job_timeout(): void
    {
        $agentTimeout = (new \ReflectionClass(SupportAgent::class))
            ->getAttributes(\Laravel\Ai\Attributes\Timeout::class)[0]
            ->newInstance()
            ->value;
        $pauses = collect(range(1, SupportTicketService::ASSISTANT_ATTEMPTS - 1))->sum();

        $this->assertLessThan(
            (new AnswerSupportTicketJob(new SupportTicket))->timeout,
            SupportTicketService::ASSISTANT_ATTEMPTS * $agentTimeout + $pauses,
        );
        $this->assertLessThan(config('queue.connections.redis.retry_after'), (new AnswerSupportTicketJob(new SupportTicket))->timeout);
    }

    public function test_the_assistant_stays_quiet_once_a_person_has_the_ticket(): void
    {
        SupportAgent::fake([['reply' => 'Hi!', 'escalate' => false]]);
        $user = User::factory()->create();
        $ticketId = $this->start($user)->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/support-tickets/{$ticketId}/escalate")
            ->assertOk()
            ->assertJsonPath('data.status', SupportTicketStatus::NEEDS_AGENT->value);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/support-tickets/{$ticketId}/messages", ['message' => 'Hello?'])
            ->assertOk()
            ->assertJsonPath('data.messages.2.author', SupportTicketMessage::AUTHOR_USER);

        $this->assertSame(1, SupportTicket::find($ticketId)->messages()->where('author', 'assistant')->count());
    }

    public function test_the_assistant_gets_earlier_messages_as_history_and_unanswered_ones_as_the_prompt(): void
    {
        SupportAgent::fake([['reply' => 'Use the Compete page.', 'escalate' => false]]);
        $ticket = SupportTicket::findOrFail($this->start(User::factory()->create())->json('data.id'));

        // Two messages in a row, before the assistant replies to either.
        $ticket->messages()->create(['author' => SupportTicketMessage::AUTHOR_USER, 'body' => 'How big can photos be?']);
        $ticket->messages()->create(['author' => SupportTicketMessage::AUTHOR_USER, 'body' => 'And what formats?']);

        $agent = new SupportAgent($ticket);

        $this->assertSame(
            ['How do I enter the challenge?', 'Use the Compete page.'],
            collect($agent->messages())->pluck('content')->all(),
        );
        $this->assertSame("How big can photos be?\n\nAnd what formats?", $agent->pendingQuestion());
    }

    public function test_a_ticket_goes_to_a_person_after_the_reply_limit(): void
    {
        config(['services.support_assistant.max_replies' => 1]);
        SupportAgent::fake([['reply' => 'First answer.', 'escalate' => false]]);
        $user = User::factory()->create();
        $ticketId = $this->start($user)->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/support-tickets/{$ticketId}/messages", ['message' => 'Still stuck'])
            ->assertJsonPath('data.status', SupportTicketStatus::NEEDS_AGENT->value)
            ->assertJsonPath('data.messages.3.body', SupportTicketService::HANDOFF_MESSAGE);
    }

    public function test_writing_on_a_resolved_ticket_reopens_it_for_the_assistant(): void
    {
        SupportAgent::fake([
            ['reply' => 'Done!', 'escalate' => false],
            ['reply' => 'Welcome back.', 'escalate' => false],
        ]);
        $user = User::factory()->create();
        $ticketId = $this->start($user)->json('data.id');

        $this->actingAs($user, 'sanctum')->postJson("/api/support-tickets/{$ticketId}/close")
            ->assertJsonPath('data.status', SupportTicketStatus::RESOLVED->value);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/support-tickets/{$ticketId}/messages", ['message' => 'One more thing'])
            ->assertJsonPath('data.status', SupportTicketStatus::OPEN->value)
            ->assertJsonPath('data.messages.3.body', 'Welcome back.');
    }

    public function test_users_only_see_their_own_tickets(): void
    {
        SupportAgent::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticketId = $this->start($owner)->json('data.id');

        $this->actingAs($other, 'sanctum')->getJson('/api/support-tickets')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->actingAs($other, 'sanctum')->getJson("/api/support-tickets/{$ticketId}")->assertNotFound();
        $this->actingAs($other, 'sanctum')
            ->postJson("/api/support-tickets/{$ticketId}/messages", ['message' => 'hi'])
            ->assertNotFound();
        $this->actingAs($other, 'sanctum')->deleteJson("/api/support-tickets/{$ticketId}")->assertNotFound();

        $this->actingAs($owner, 'sanctum')->getJson('/api/support-tickets')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticketId);
    }

    public function test_the_prompt_includes_live_settings_and_the_customers_details(): void
    {
        SupportAgent::fake();
        $user = User::factory()->create(['name' => 'Wanjiku Test']);
        $this->start($user);

        SupportAgent::assertPrompted(function ($prompt) {
            $instructions = (string) $prompt->agent->instructions();

            return str_contains($instructions, '1st place: KES 500')
                && str_contains($instructions, 'Name: Wanjiku Test')
                && str_contains($instructions, 'The weekly photo challenge');
        });
    }

    public function test_knowledge_base_limits_come_from_the_code(): void
    {
        $knowledgeBase = (new SupportAgent(new SupportTicket))->knowledgeBase();

        $this->assertStringNotContainsString('{{', $knowledgeBase);
        $this->assertStringContainsString('up to 25 MB', $knowledgeBase);
        $this->assertStringContainsString('up to '.Photo::MAX_IMAGES_PER_ENTRY.' photos per entry', $knowledgeBase);
    }
}
