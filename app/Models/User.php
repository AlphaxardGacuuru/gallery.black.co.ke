<?php

namespace App\Models;

use App\Enums\EmailNotificationCategory;
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
        'verified' => 'boolean',
        'is_seed' => 'boolean',
        'flagged_at' => 'datetime',
        'flag_refuted_at' => 'datetime',
        'phone_requested_at' => 'datetime',
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
        return $query->whereNull('flagged_at');
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

    public function isFlagged(): bool
    {
        return $this->flagged_at !== null;
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
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
