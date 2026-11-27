<?php

namespace App\Models;

use App\Enums\EmailNotificationCategory;
use App\Enums\VerificationReason;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'gender', 'google_id', 'avatar', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements CanResetPassword, MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasPushSubscriptions, HasRoles, HasUuids, Notifiable, TwoFactorAuthentication;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'object',
        'email_verified_at' => 'datetime',
        'verification' => 'array',
        'flag' => 'array',
        'phone_request' => 'array',
        'is_seed' => 'boolean',
        'last_active_at' => 'datetime',
        'updated_at' => 'datetime:d M Y',
        'created_at' => 'datetime:d M Y h:i:s',
    ];

    /**
     * The largest profile picture a user can upload, in kilobytes.
     */
    public const MAX_AVATAR_KB = 5120;

    /**
     * Where uploaded profile pictures live on the public disk.
     */
    public const AVATAR_UPLOAD_DIRECTORY = 'avatars/uploads';

    /**
     * Setting that has an AI agent screen each new sign-up, see
     * screensNewSignups().
     */
    public const SETTING_SCREEN_NEW_SIGNUPS = 'screen_new_signups';

    /**
     * flag.source for a flag an admin set from the users page.
     */
    public const FLAG_SOURCE_ADMIN = 'admin';

    /**
     * flag.source for a flag the sign-up screening agent set. Adding a
     * phone number lifts it (see isFlaggedUntilPhone()).
     */
    public const FLAG_SOURCE_SIGNUP_SCREENING = 'signup_screening';

    /**
     * flag.source for a flag set by changing to a new phone number. Paying
     * the verification fee from the new number lifts it (see
     * isFlaggedUntilPhoneVerified()).
     */
    public const FLAG_SOURCE_PHONE_CHANGE = 'phone_change';

    public const PHONE_CHANGE_FLAG_REASON = 'New phone number not verified';

    /**
     * Setting holding the fee for confirming a new phone number, see
     * phoneVerificationFee().
     */
    public const SETTING_PHONE_VERIFICATION_FEE = 'phone_verification_fee';

    public const DEFAULT_PHONE_VERIFICATION_FEE = 10;

    protected string $guard_name = 'web';

    /**
     * Get the name of the guard associated with the user model.
     */
    public function getDefaultGuardName(): string
    {
        return 'web';
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->phone && $user->isDirty('phone')) {
                $normalized = substr_replace($user->phone, '254', 0, -9);
                $user->hashed_phone = hash('sha256', $normalized);
            }
        });
    }

    /**
     * Accesors.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => static::capitalizeName($value),
        );
    }

    /**
     * Uppercase the first letter of each word, including after a hyphen or
     * apostrophe ("mary-jane o'brien" to "Mary-Jane O'Brien"). The rest is
     * left alone, so names like "McDonald" keep their capitals.
     */
    public static function capitalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return Str::of($name)
            ->replaceMatches("/(^|[\s\-'’])(\p{Ll})/u", fn (array $matches) => $matches[1].mb_strtoupper($matches[2]))
            ->toString();
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn($value) => preg_match("/https/", $value) ? $value : "/storage/" . $value
        );
    }

    /**
     * Whether the user picked their own profile picture, which Google
     * sign-in then leaves alone.
     */
    public function hasUploadedAvatar(): bool
    {
        return Str::of((string) $this->getRawOriginal('avatar'))
            ->startsWith(static::AVATAR_UPLOAD_DIRECTORY . '/');
    }

    /**
     * Whether the user has the verified badge, from the `verification` JSON
     * column. Change it with markVerified() and markUnverified().
     */
    protected function verified(): Attribute
    {
        return Attribute::get(fn () => (bool) ($this->verification['verified'] ?? false));
    }

    /**
     * Why the user got the verified badge, when they have it.
     */
    protected function verificationReason(): Attribute
    {
        return Attribute::get(fn () => $this->verified
            ? VerificationReason::tryFrom($this->verification['reason'] ?? '')
            : null);
    }

    /**
     * The fields below read the `flag` and `phone_request` JSON columns
     * under their old column names. Write to the JSON columns themselves.
     */
    protected function flaggedAt(): Attribute
    {
        return Attribute::get(fn () => static::jsonDate($this->flag['at'] ?? null));
    }

    protected function flagReason(): Attribute
    {
        return Attribute::get(fn () => $this->flag['reason'] ?? null);
    }

    protected function flagSource(): Attribute
    {
        return Attribute::get(fn () => $this->flag['source'] ?? null);
    }

    protected function flagRefutedAt(): Attribute
    {
        return Attribute::get(fn () => static::jsonDate($this->flag['refuted_at'] ?? null));
    }

    protected function phoneRequestsCount(): Attribute
    {
        return Attribute::get(fn () => (int) ($this->phone_request['count'] ?? 0));
    }

    protected function phoneRequestedAt(): Attribute
    {
        return Attribute::get(fn () => static::jsonDate($this->phone_request['at'] ?? null));
    }

    private static function jsonDate(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    protected function emailVerifiedAt(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? Carbon::parse($value)->format('d M Y') : null,
        );
    }

    protected function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn($value) => Carbon::parse($value)->format('d M Y h:i:s'),
        );
    }

    /**
     * Real people only, leaving out the seed accounts that post Unsplash
     * photos into each competition (see PostSeedPhotos). Their emails
     * aren't real inboxes, so anything that notifies users should use this.
     */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_seed', false);
    }

    /**
     * Users an admin hasn't flagged for suspected abuse. Only their likes
     * count (see Photo::recountLikes()).
     */
    public function scopeUnflagged(Builder $query): Builder
    {
        return $query->whereNull('flag');
    }

    /**
     * Users an admin or sign-up screening flagged for suspected abuse.
     */
    public function scopeFlagged(Builder $query): Builder
    {
        return $query->whereNotNull('flag');
    }

    /**
     * Users with the verified badge, or without it when $verified is false.
     */
    public function scopeVerified(Builder $query, bool $verified = true): Builder
    {
        return $verified
            ? $query->where('verification->verified', true)
            : $query->where(fn (Builder $query) => $query
                ->whereNull('verification')
                ->orWhere('verification->verified', false));
    }

    /**
     * Users whose name or email contains $term. Grouped, so it can be
     * combined with other conditions.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%"));
    }

    /**
     * Give the user the verified badge for $reason, granted by $by (null
     * when the app did it, e.g. for winning). False if they already had it.
     */
    public function markVerified(VerificationReason $reason, ?User $by = null): bool
    {
        if ($this->verified) {
            return false;
        }

        $this
            ->forceFill(['verification' => [
                'verified' => true,
                'at' => now(),
                'reason' => $reason->value,
                'by' => $by?->id,
            ]])
            ->save();

        return true;
    }

    /**
     * Take the verified badge away, recording when and by whom. False if
     * they didn't have it.
     */
    public function markUnverified(?User $by = null): bool
    {
        if (! $this->verified) {
            return false;
        }

        $this
            ->forceFill(['verification' => [
                'verified' => false,
                'at' => now(),
                'by' => $by?->id,
            ]])
            ->save();

        return true;
    }

    public function isFlagged(): bool
    {
        return $this->flagged_at !== null;
    }

    /**
     * Whether the sign-up screening agent flagged the user, which adding a
     * phone number lifts by itself. An admin's flag needs a review.
     */
    public function isFlaggedUntilPhone(): bool
    {
        return $this->isFlagged() && $this->flag_source === self::FLAG_SOURCE_SIGNUP_SCREENING;
    }

    /**
     * Whether an AI agent screens new sign-ups for fake or duplicate
     * accounts. On until an admin switches it off on the users page.
     */
    public static function screensNewSignups(): bool
    {
        return (bool) (Setting::query()
            ->where('key', self::SETTING_SCREEN_NEW_SIGNUPS)
            ->value('value') ?? true);
    }

    /**
     * Whether changing to a new number flagged the user until they pay
     * the verification fee from it (see PhoneVerification).
     */
    public function isFlaggedUntilPhoneVerified(): bool
    {
        return $this->isFlagged() && $this->flag_source === self::FLAG_SOURCE_PHONE_CHANGE;
    }

    /**
     * What a user who changes their M-Pesa number pays from the new one to
     * lift the flag the change sets. Set on the admin users page; 0 lets
     * people change their number without paying or being flagged.
     */
    public static function phoneVerificationFee(): int
    {
        return (int) (Setting::query()
            ->where('key', self::SETTING_PHONE_VERIFICATION_FEE)
            ->value('value') ?? self::DEFAULT_PHONE_VERIFICATION_FEE);
    }

    /**
     * Why a flag stops the user entering the challenge (submitting, adding
     * photos or buying slots), or null when they aren't flagged. Flagged
     * accounts can't win, so they can't enter either.
     */
    public function flaggedEntryMessage(): ?string
    {
        return match (true) {
            $this->isFlaggedUntilPhoneVerified() => 'Confirm your new M-Pesa phone number in Settings > Profile before entering the challenge.',
            $this->isFlagged() => 'Your account is flagged, so you can\'t enter the challenge right now.',
            default => null,
        };
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function phoneVerifications(): HasMany
    {
        return $this->hasMany(PhoneVerification::class);
    }

    /**
     * Photos this user saved, listed under Saved on their profile.
     */
    public function photoBookmarks(): HasMany
    {
        return $this->hasMany(PhotoBookmark::class);
    }

    /**
     * Other users this user referred, who signed up via their referral link.
     */
    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /**
     * Who referred this user, if anyone (a user can only be referred once).
     */
    public function referral(): HasOne
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }

    public function logins(): HasMany
    {
        return $this->hasMany(UserLogin::class);
    }

    /*
     * Custom functions
     */

    /**
     * How long last_active_at may lag behind, so an active user only costs
     * one write every few minutes (see TrackUserActivity).
     */
    public const ACTIVITY_INTERVAL_MINUTES = 5;

    /**
     * Note that the user is using the app, at most once every
     * ACTIVITY_INTERVAL_MINUTES. Leaves updated_at alone, since nothing
     * about the account itself changed.
     */
    public function markActive(): void
    {
        if ($this->last_active_at?->gt(now()->subMinutes(self::ACTIVITY_INTERVAL_MINUTES))) {
            return;
        }

        static::withoutTimestamps(fn () => $this
            ->forceFill(['last_active_at' => now()])
            ->saveQuietly());
    }

    /**
     * Real users who used the app in the last day, week and month.
     *
     * @return array{day: int, week: int, month: int}
     */
    public static function activeCounts(): array
    {
        $counts = static::query()
            ->real()
            ->toBase()
            ->selectRaw('SUM(CASE WHEN last_active_at >= ? THEN 1 ELSE 0 END) as day', [now()->subDay()])
            ->selectRaw('SUM(CASE WHEN last_active_at >= ? THEN 1 ELSE 0 END) as week', [now()->subWeek()])
            ->selectRaw('SUM(CASE WHEN last_active_at >= ? THEN 1 ELSE 0 END) as month', [now()->subMonth()])
            ->first();

        return [
            'day' => (int) $counts->day,
            'week' => (int) $counts->week,
            'month' => (int) $counts->month,
        ];
    }

    /**
     * The admin portal is open to the configured admin email and to the
     * password login account (see config/admin.php).
     */
    public function isAdmin(): bool
    {
        return in_array($this->email, [config('admin.email'), config('admin.password_login_email')], true);
    }

    /**
     * Whether this user still wants emails for the given category — opt-out
     * by default (true) so users created before a category existed, or who
     * never touched their preferences, keep receiving it.
     */
    public function wantsEmail(EmailNotificationCategory $category): bool
    {
        return (bool) ($this->settings?->{$category->settingsKey()} ?? true);
    }

    /**
     * A signed, no-login-required link that turns the given category off —
     * embedded in every email of that category so it can be unsubscribed
     * from with one click.
     */
    public function unsubscribeUrlFor(EmailNotificationCategory $category): string
    {
        return URL::signedRoute('unsubscribe.show', [
            'user' => $this->getKey(),
            'category' => $category->value,
        ]);
    }
}
