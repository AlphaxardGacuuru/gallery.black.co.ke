<?php

namespace App\Http\Resources;

use App\Models\SupportTicketMessage;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $canDelete = Carbon::parse($this->getRawOriginal('created_at'))
            ->gt(now()->subHour());

        return [
            "id" => $this->id,
            "number" => $this->number,
            "userId" => $this->user_id,
            "userName" => $this->user?->name,
            "userAvatar" => $this->user?->avatar,
            "userPhone" => $this->user?->phone,
            "category" => $this->category,
            "subject" => $this->subject,
            "priority" => $this->priority,
            "description" => $this->description,
            "attachments" => $this->attachments,
            "status" => $this->status,
            "canDelete" => $canDelete,
            "createdAt" => $this->created_at,
            "lastActivityAt" => Carbon::parse($this->getRawOriginal('updated_at'))->toIso8601String(),
            "latestMessage" => $this->whenLoaded(
                'latestMessage',
                fn () => $this->latestMessage ? $this->presentMessage($this->latestMessage) : null,
            ),
            "messages" => $this->whenLoaded(
                'messages',
                fn () => $this->messages->map(fn ($message) => $this->presentMessage($message)),
            ),
        ];
    }

    private function presentMessage(SupportTicketMessage $message): array
    {
        return [
            "id" => $message->id,
            "author" => $message->author,
            "body" => $message->body,
            "createdAt" => $message->created_at?->toIso8601String(),
        ];
    }
}
