<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An admin asked a user without an M-Pesa phone number to add one, so
 * prizes and payouts can reach them.
 */
class PhoneNumberRequestedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user)
    {
        //
    }
}
