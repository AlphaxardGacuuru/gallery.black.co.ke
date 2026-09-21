<?php

namespace App\Models;

use App\Enums\EmailNotificationCategory;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
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
        'updated_at' => 'datetime:d M Y',
        'created_at' => 'datetime:d M Y h:i:s',
    ];

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
     * Other users this user referred, who signed up via their referral link.
     */
    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /*
     * Custom functions
     */

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
