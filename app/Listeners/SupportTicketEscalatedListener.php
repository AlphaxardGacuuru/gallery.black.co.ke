<?php

namespace App\Listeners;

use App\Events\SupportTicketEscalatedEvent;
use App\Models\User;
use App\Notifications\AdminSupportTicketEscalatedNotification;

class SupportTicketEscalatedListener
{
    public function handle(SupportTicketEscalatedEvent $event): void
    {
        $this->notifyAdmin($event);
    }

    private function notifyAdmin(SupportTicketEscalatedEvent $event): void
    {
        User::where('email', config('admin.email'))
            ->first()
            ?->notify(new AdminSupportTicketEscalatedNotification($event->ticket));
    }
}
