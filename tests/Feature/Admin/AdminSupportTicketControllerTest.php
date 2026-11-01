<?php

namespace Tests\Feature\Admin;

use App\Ai\Agents\SupportAgent;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketRepliedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminSupportTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => config('admin.email')]);
    }

    private function escalatedTicket(User $customer): SupportTicket
    {
        SupportAgent::fake([['reply' => 'Passing this to the team.', 'escalate' => true]]);

        $id = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/support-tickets', ['message' => 'My prize never arrived'])
            ->json('data.id');

        return SupportTicket::findOrFail($id);
    }

    public function test_non_admins_cant_open_the_inbox(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/support-tickets')
            ->assertForbidden();
    }

    public function test_the_inbox_lists_tickets_with_counts_per_status(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $ticket = $this->escalatedTicket(User::factory()->create(['name' => 'Otieno']));

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/support-tickets?status=needs_agent')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ticket->id)
            ->assertJsonPath('data.0.userName', 'Otieno')
            ->assertJsonPath('data.0.latestMessage.body', 'Passing this to the team.')
            ->assertJsonPath('meta.counts.needs_agent', 1)
            ->assertJsonPath('meta.counts.open', 0);
    }

    public function test_an_admin_reply_notifies_the_customer_and_keeps_the_ai_out(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $ticket = $this->escalatedTicket($customer);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/support-tickets/{$ticket->id}/messages", ['message' => 'Sent it just now.'])
            ->assertOk()
            ->assertJsonPath('data.status', SupportTicketStatus::IN_PROGRESS->value)
            ->assertJsonPath('data.messages.2.author', 'agent');

        Notification::assertSentTo($customer, SupportTicketRepliedNotification::class);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/support-tickets/{$ticket->id}/messages", ['message' => 'Thanks!'])
            ->assertJsonCount(4, 'data.messages');
    }

    public function test_an_admin_can_resolve_a_ticket(): void
    {
        Notification::fake();
        $ticket = $this->escalatedTicket(User::factory()->create());

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/support-tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }
}
