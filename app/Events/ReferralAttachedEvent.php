<?php

namespace App\Events;

use App\Models\Referral;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An admin credited a user to a referrer by hand, for someone who signed up
 * without a referral link (see AdminReferralController::attach). Signups
 * through the link go through Registered/SendNewUserSignupNotifications.
 */
class ReferralAttachedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public Referral $referral) {}
}
