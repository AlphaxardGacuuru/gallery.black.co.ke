<?php

namespace App\Listeners;

use App\Models\Referral;
use App\Models\User;
use App\Notifications\AdminNewUserSignupNotification;
use App\Notifications\ReferralSignupNotification;
use Illuminate\Auth\Events\Registered;

class SendNewUserSignupNotifications
{
    public function __construct()
    {
        //
    }

    public function handle(Registered $event): void
    {
        $admin = User::where('email', config('admin.email'))->first();
        $referrer = Referral::where('referred_id', $event->user->id)->first()?->referrer;

        if ($admin && $admin->isNot($event->user)) {
            $admin->notify(new AdminNewUserSignupNotification($event->user, $referrer));
        }

        if ($referrer) {
            $referrer->notify(new ReferralSignupNotification($event->user));
        }
    }
}
