<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An in-app modal (ad, announcement, nudge) picked per user by
 * NoticeService::next(). Text may contain {variables}, filled in by
 * NoticeVariables when served.
 */
class Notice extends Model
{
    /** @use HasFactory<\Database\Factories\NoticeFactory> */
    use HasFactory, HasUuids;

    public const AUDIENCE_ALL = 'all';

    /** Users who have never referred anyone. */
    public const AUDIENCE_NEVER_REFERRED = 'never_referred';

    /** Users without a photo in the active competition. */
    public const AUDIENCE_NOT_ENTERED = 'not_entered_active_competition';

    public const AUDIENCES = [
        self::AUDIENCE_ALL,
        self::AUDIENCE_NEVER_REFERRED,
        self::AUDIENCE_NOT_ENTERED,
    ];

    /** Must match the icon map in resources/js/components/notice-modal.tsx. */
    public const ICONS = ['megaphone', 'gift', 'trophy', 'camera', 'sparkles', 'user-plus'];

    /** Global limits, stored in settings and editable from admin. */
    public const DEFAULT_DAILY_LIMIT = 2;

    public const DEFAULT_MIN_GAP_HOURS = 4;

    protected $fillable = [
        'key',
        'title',
        'body',
        'icon',
        'primary_label',
        'primary_url',
        'secondary_label',
        'audience',
        'priority',
        'cooldown_hours',
        'max_impressions',
        'stop_after_click',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'priority' => 'integer',
        'cooldown_hours' => 'integer',
        'max_impressions' => 'integer',
        'stop_after_click' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(NoticeEvent::class);
    }

    /**
     * Switched on and inside its start/end window (either end may be open).
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn(Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn(Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * @return array{dailyLimit: int, minGapHours: int}
     */
    public static function limits(): array
    {
        return [
            'dailyLimit' => (int) (Setting::query()->where('key', 'notices_daily_limit')->value('value')
                ?? self::DEFAULT_DAILY_LIMIT),
            'minGapHours' => (int) (Setting::query()->where('key', 'notices_min_gap_hours')->value('value')
                ?? self::DEFAULT_MIN_GAP_HOURS),
        ];
    }
}
