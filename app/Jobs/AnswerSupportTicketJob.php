<?php

namespace App\Jobs;

use App\Http\Services\SupportTicketService;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class AnswerSupportTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Waiting on another reply to the same ticket releases the job back to
     * the queue, and each release counts as an attempt.
     */
    public int $tries = 10;

    /**
     * Room for every AI attempt (SupportTicketService::ASSISTANT_ATTEMPTS
     * times SupportAgent's timeout, plus pauses), and under the queue's
     * retry_after of 90 seconds so a slow job isn't run twice.
     */
    public int $timeout = 80;

    public function __construct(public SupportTicket $ticket) {}

    /**
     * One reply per ticket at a time. If the customer sends two messages
     * quickly, the second job waits, then answers whatever the first one
     * didn't (or nothing, if the first reply covered both).
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->ticket->id))
                ->releaseAfter(5)
                ->expireAfter(120),
        ];
    }

    public function handle(SupportTicketService $service): void
    {
        $service->answerWithAssistant($this->ticket);
    }
}
