<?php

namespace App\Models;

use App\Enums\SupportTicketStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
        'user_read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $supportTicket) {
            $supportTicket->number = (static::max('number') ?? 0) + 1;
        });
    }

    protected function number(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => 'SP-'.str_pad($value, 4, '0', STR_PAD_LEFT),
        );
    }

    protected function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    /*
    * Scopes
    */

    /** Tickets with a reply the customer hasn't opened yet. */
    public function scopeUnreadByCustomer(Builder $query): Builder
    {
        return $query->whereHas('messages', fn (Builder $query) => $query->unreadByCustomer());
    }

    /**
     * Tickets waiting on a person: handed over and not answered yet, or
     * answered but the customer has written again since.
     */
    public function scopeWaitingOnPerson(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query->where('status', SupportTicketStatus::NEEDS_AGENT->value)
            ->orWhere(fn (Builder $query) => $query->where('status', SupportTicketStatus::IN_PROGRESS->value)
                ->whereHas('latestMessage', fn (Builder $query) => $query->where('author', SupportTicketMessage::AUTHOR_USER))));
    }

    /**
     * Record that the customer has seen the conversation. Skips the
     * timestamps so reading doesn't bump the ticket up the admin inbox.
     */
    public function markReadByCustomer(): void
    {
        $this->timestamps = false;
        $this->forceFill(['user_read_at' => now()])
            ->save();
        $this->timestamps = true;
    }

    /*
    * Relationships
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        // Ids are ordered UUIDs, which breaks ties between messages created
        // in the same second.
        return $this->hasMany(SupportTicketMessage::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function latestMessage()
    {
        return $this->hasOne(SupportTicketMessage::class)->latestOfMany(['created_at', 'id']);
    }
}
