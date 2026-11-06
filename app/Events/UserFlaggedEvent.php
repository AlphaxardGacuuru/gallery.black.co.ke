<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An admin flagged a user for suspected abuse. Not fired when the flag is
 * lifted.
 */
class UserFlaggedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user)
    {
        //
    }
}
