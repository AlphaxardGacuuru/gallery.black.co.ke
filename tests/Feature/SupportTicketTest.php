<?php

namespace Tests\Feature;

use App\Ai\Agents\SupportAgent;
use App\Enums\SupportTicketStatus;
use App\Http\Services\SupportTicketService;
use App\Models\Photo;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\AdminSupportTicketEscalatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

        Notification::assertSentTo($admin, AdminSupportTicketEscalatedNotification::class);
    }

    public function test_an_ai_failure_hands_the_ticket_to_a_person(): void
    {
        SupportAgent::fake(fn () => throw new RuntimeException('Gemini is down'));

        $ticketId = $this->start(User::factory()->create())->json('data.id');
        $ticket = SupportTicket::find($ticketId);

        $this->assertSame(SupportTicketStatus::NEEDS_AGENT->value, $ticket->status);
        $this->assertSame(SupportTicketService::HANDOFF_MESSAGE, $ticket->latestMessage->body);
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
