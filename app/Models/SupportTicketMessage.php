<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketMessage extends Model
{
    use HasUuids;

    /** The customer who opened the ticket. */
    public const AUTHOR_USER = 'user';

    /** The AI support assistant. */
    public const AUTHOR_ASSISTANT = 'assistant';

    /** A human on the support team (the admin). */
    public const AUTHOR_AGENT = 'agent';

    protected $guarded = [];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
