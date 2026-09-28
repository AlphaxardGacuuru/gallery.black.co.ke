<?php

namespace App\Enums;

enum EmailNotificationCategory: string
{
    case COMPETITION_STARTED = 'competition-started';
    case COMPETITION_WON = 'competition-won';
    case REFERRAL_SIGNED_UP = 'referral-signed-up';

    public function settingsKey(): string
    {
        return match ($this) {
            self::COMPETITION_STARTED => 'competitionStartedNotification',
            self::COMPETITION_WON => 'competitionWonNotification',
            self::REFERRAL_SIGNED_UP => 'referralSignupNotification',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::COMPETITION_STARTED => 'challenge announcement',
            self::COMPETITION_WON => 'challenge winner',
            self::REFERRAL_SIGNED_UP => 'referral signup',
        };
    }
}
