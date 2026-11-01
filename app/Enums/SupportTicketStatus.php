<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    // The AI assistant answers while a ticket is open. Once it's handed to
    // a person (needs_agent) or a person has replied (in_progress), the
    // assistant stays out of the conversation.
    case OPEN = 'open';
    case NEEDS_AGENT = 'needs_agent';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
