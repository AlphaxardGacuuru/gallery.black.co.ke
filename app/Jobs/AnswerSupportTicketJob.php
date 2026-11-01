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
