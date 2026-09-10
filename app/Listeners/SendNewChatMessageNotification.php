<?php

namespace App\Listeners;

use App\Events\ChatMessageSent;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendNewChatMessageNotification implements ShouldQueue
{
    public function handle(ChatMessageSent $event): void
    {
        $message = $event->message;

        $recipients = $message->conversation->participants()
            ->where('users.id', '!=', $message->sender_id)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NewChatMessageNotification($message));
        }
    }
}
