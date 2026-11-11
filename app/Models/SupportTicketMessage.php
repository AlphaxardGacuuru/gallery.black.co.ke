<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Replies (from the assistant or a person) the customer hasn't seen:
     * newer than when they last opened the ticket. Only usable inside a
     * query on the ticket (whereHas, withExists), since it compares with
     * the ticket's own user_read_at.
     */
    public function scopeUnreadByCustomer(Builder $query): Builder
    {
        return $query->where('author', '!=', self::AUTHOR_USER)
            ->where(fn (Builder $query) => $query->whereNull('support_tickets.user_read_at')
                ->orWhereColumn('support_ticket_messages.created_at', '>', 'support_tickets.user_read_at'));
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
