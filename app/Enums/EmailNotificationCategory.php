<?php

namespace App\Enums;

enum EmailNotificationCategory: string
{
    case COMPETITION_STARTED = 'competition-started';
    case COMPETITION_WON = 'competition-won';

    public function settingsKey(): string
    {
        return match ($this) {
            self::COMPETITION_STARTED => 'competitionStartedNotification',
            self::COMPETITION_WON => 'competitionWonNotification',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::COMPETITION_STARTED => 'challenge announcement',
            self::COMPETITION_WON => 'challenge winner',
        };
    }
}
