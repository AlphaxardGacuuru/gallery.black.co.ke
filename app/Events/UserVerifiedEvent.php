<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A user just got the verified badge, either by placing in a challenge or
 * because an admin granted it. Not fired when it's revoked.
 */
class UserVerifiedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user)
    {
        //
    }
}
