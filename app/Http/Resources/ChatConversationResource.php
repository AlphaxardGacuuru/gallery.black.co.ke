<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $me = $request->user();
        $otherParticipant = $this->participants->firstWhere('id', '!=', $me->id);
        $myPivot = $this->participants->firstWhere('id', $me->id)?->pivot;

        // The eager-loaded path (index()) never needs the cutoff applied
        // here: notDeletedBy() only lets a conversation through when its
        // truly-latest message is already after my cutoff, so that message
        // is safe to preview as-is. The lazy path (show(), reachable via a
        // direct link even when nothing has arrived past my cutoff yet)
        // needs it explicitly, or it could preview a message from before
        // my own delete.
        $lastMessage = $this->relationLoaded('messages')
            ? $this->messages->first()
            : $this->messages()
                ->when($myPivot?->deleted_at, fn ($query, $deletedAt) => $query->where('created_at', '>', $deletedAt))
                ->latest()
                ->first();

        $unreadCount = $this->messages()
            ->where('sender_id', '!=', $me->id)
            ->when($myPivot?->last_read_at, fn ($query, $lastReadAt) => $query->where('created_at', '>', $lastReadAt))
            // Never count messages from before my own delete cutoff — those
            // aren't part of my visible history at all (see
            // ChatConversationService::show()).
            ->when($myPivot?->deleted_at, fn ($query, $deletedAt) => $query->where('created_at', '>', $deletedAt))
            ->count();

        return [
            'id' => $this->id,
            'otherUser' => $otherParticipant ? [
                'id' => $otherParticipant->id,
                'name' => $otherParticipant->name,
                'email' => $otherParticipant->email,
                'avatar' => $otherParticipant->avatar,
                'verified' => (bool) $otherParticipant->verified,
                'lastSeenAt' => $otherParticipant->last_seen_at,
            ] : null,
            'lastMessage' => $lastMessage ? [
                'body' => $lastMessage->body,
                'senderId' => $lastMessage->sender_id,
                'createdAt' => $lastMessage->created_at,
            ] : null,
            'unreadCount' => $unreadCount,
            'lastMessageAt' => $this->last_message_at,
            'isArchived' => (bool) $myPivot?->archived_at,
        ];
    }
}
