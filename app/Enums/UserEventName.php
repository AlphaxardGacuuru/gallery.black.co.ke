<?php

namespace App\Enums;

/**
 * Everything recorded in user_events: the tracking plan. Each description
 * says what the event means and which fields it fills, for anyone (or any
 * agent) reading the data.
 */
enum UserEventName: string
{
    case SIGNED_UP = 'user.signed_up';
    case LOGGED_IN = 'user.logged_in';
    case PHONE_ADDED = 'user.phone_added';
    case VERIFIED = 'user.verified';
    case FLAGGED = 'user.flagged';
    case REFERRED = 'user.referred';
    case PHOTO_SUBMITTED = 'photo.submitted';
    case PHOTO_LIKED = 'photo.liked';
    case PHOTO_BOOKMARKED = 'photo.bookmarked';
    case PHOTO_UNBOOKMARKED = 'photo.unbookmarked';
    case PHOTO_PURCHASED = 'photo.purchased';
    case EXTRA_ENTRY_PURCHASED = 'extra_entry.purchased';
    case EXTRA_PHOTO_PURCHASED = 'extra_photo.purchased';
    case PRIZE_WON = 'prize.won';
    case SUPPORT_MESSAGE_SENT = 'support.message_sent';
    case APP_INSTALLED = 'app.installed';
    case PAGE_VIEWED = 'page.viewed';
    case PHOTO_SHARED = 'photo.shared';
    case REFERRAL_SHARED = 'referral.shared';

    public function description(): string
    {
        return match ($this) {
            self::SIGNED_UP => 'Created an account. source: google or password.',
            self::LOGGED_IN => 'Signed in. source: google or password.',
            self::PHONE_ADDED => 'Added an M-Pesa phone number for the first time.',
            self::VERIFIED => 'Got the verified badge. properties.reason: why.',
            self::FLAGGED => 'Was flagged for suspected abuse. source: admin or signup_screening.',
            self::REFERRED => 'Was credited to a referrer. source: link or admin. properties.referrer_id.',
            self::PHOTO_SUBMITTED => 'Entered a photo into a challenge. photo_id, competition_id, properties.images: photos in the entry.',
            self::PHOTO_LIKED => 'Liked a photo. photo_id, competition_id. source: app or push_action.',
            self::PHOTO_BOOKMARKED => 'Saved a photo to their profile. photo_id, competition_id.',
            self::PHOTO_UNBOOKMARKED => 'Removed a saved photo. photo_id, competition_id.',
            self::PHOTO_PURCHASED => 'Bought a full-resolution photo. photo_id, competition_id, properties.amount in KES.',
            self::EXTRA_ENTRY_PURCHASED => 'Paid for a second entry in a challenge. competition_id, properties.amount in KES.',
            self::EXTRA_PHOTO_PURCHASED => 'Paid for one more photo in an entry. competition_id, properties.amount in KES.',
            self::PRIZE_WON => 'Placed in a challenge. photo_id, competition_id, properties.position and properties.amount in KES.',
            self::SUPPORT_MESSAGE_SENT => 'Sent a message to support. properties.ticket_id.',
            self::APP_INSTALLED => 'Installed the app to their home screen.',
            self::PAGE_VIEWED => 'Opened a page. path: the URL path. properties.route: the page pattern, e.g. /users/$id. Guests have only anonymous_id.',
            self::PHOTO_SHARED => 'Shared a photo. photo_id, competition_id. source: share_sheet or copied_link.',
            self::REFERRAL_SHARED => 'Shared their referral link. source: share_sheet or copied_link.',
        };
    }

    /**
     * The events the browser may send to /api/events. Everything else is
     * recorded on the server, where it can't be faked.
     *
     * @return array<int, self>
     */
    public static function clientEvents(): array
    {
        return [self::PAGE_VIEWED, self::PHOTO_SHARED, self::REFERRAL_SHARED];
    }
}
