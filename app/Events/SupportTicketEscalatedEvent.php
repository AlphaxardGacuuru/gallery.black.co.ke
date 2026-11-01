<?php

namespace App\Events;

use App\Models\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A support ticket was handed from the AI assistant to a person, because
 * the customer asked for one or the assistant couldn't help.
 */
class SupportTicketEscalatedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public SupportTicket $ticket)
    {
        //
    }
}
