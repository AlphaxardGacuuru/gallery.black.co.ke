<?php

namespace App\Listeners;

use App\Enums\SupportTicketStatus;
use App\Events\SupportTicketMessageCreatedEvent;
use App\Jobs\AnswerSupportTicketJob;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\AdminSupportTicketMessageNotification;
use App\Notifications\SupportTicketRepliedNotification;

class SupportTicketMessageCreatedListener
{
    public function handle(SupportTicketMessageCreatedEvent $event): void
    {
        $event->message->loadMissing('ticket.user');

        $this->answerWithAssistant($event);
        $this->notifyCustomer($event);
        $this->notifyAdmin($event);
    }

    /**
     * Customer messages on a ticket the AI is still handling get an AI
     * reply. Once a person has the ticket, the assistant stays out.
     */
    private function answerWithAssistant(SupportTicketMessageCreatedEvent $event): void
    {
        $message = $event->message;

        if ($message->author !== SupportTicketMessage::AUTHOR_USER) {
            return;
        }

        if ($message->ticket->status !== SupportTicketStatus::OPEN->value) {
            return;
        }

        AnswerSupportTicketJob::dispatch($message->ticket);
    }

    /**
     * The customer is usually still on screen for AI replies, but a
     * person's reply can come hours later, so that one gets a notification.
     */
    private function notifyCustomer(SupportTicketMessageCreatedEvent $event): void
    {
        if ($event->message->author !== SupportTicketMessage::AUTHOR_AGENT) {
            return;
        }

        $event->message->ticket->user?->notify(new SupportTicketRepliedNotification($event->message));
    }

    /**
     * Once a person has the ticket, every customer message waits on them,
     * so each one is pushed to the admin. While the assistant is still
     * answering, the admin only hears about it if it escalates.
     */
    private function notifyAdmin(SupportTicketMessageCreatedEvent $event): void
    {
        $message = $event->message;

        if ($message->author !== SupportTicketMessage::AUTHOR_USER) {
            return;
        }

        $handledByPerson = [SupportTicketStatus::NEEDS_AGENT->value, SupportTicketStatus::IN_PROGRESS->value];

        if (! in_array($message->ticket->status, $handledByPerson, true)) {
            return;
        }

        User::where('email', config('admin.email'))
            ->first()
            ?->notify(new AdminSupportTicketMessageNotification($message));
    }
}
