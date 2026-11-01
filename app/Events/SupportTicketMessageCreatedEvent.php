<?php

namespace App\Events;

use App\Models\SupportTicketMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message was added to a support conversation, by the customer, the AI
 * assistant or a person on the support team.
 */
class SupportTicketMessageCreatedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public SupportTicketMessage $message)
    {
        //
    }
}
