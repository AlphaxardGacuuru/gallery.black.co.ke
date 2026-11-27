<?php

namespace App\Enums;

/**
 * Why a user got the verified badge, kept in users.verification.
 */
enum VerificationReason: string
{
    case COMPETITION_WINNER = 'competition_winner';
    case NEW_SIGNUP = 'new_signup';
    case IDENTITY_CONFIRMED = 'identity_confirmed';
    case KNOWN_PHOTOGRAPHER = 'known_photographer';
    case PUBLIC_FIGURE = 'public_figure';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::COMPETITION_WINNER => 'Won a challenge',
            self::NEW_SIGNUP => 'Approved from the new sign-up notification',
            self::IDENTITY_CONFIRMED => 'Identity confirmed',
            self::KNOWN_PHOTOGRAPHER => 'Known photographer',
            self::PUBLIC_FIGURE => 'Public figure or brand',
            self::OTHER => 'Other',
        };
    }

    /**
     * The reasons an admin picks from when verifying on the users page.
     * NEW_SIGNUP only comes from the push notification's button.
     *
     * @return array<int, self>
     */
    public static function adminChoices(): array
    {
        return collect(self::cases())
            ->reject(fn (self $reason) => $reason === self::NEW_SIGNUP)
            ->values()
            ->all();
    }
}
