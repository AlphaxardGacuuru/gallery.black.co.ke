<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoCompetition extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoCompetitionFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    protected $fillable = [
        'starts_at',
        'ends_at',
        'status',
        'prize_amount',
        'winner_photo_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'prize_amount' => 'integer',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'competition_id');
    }

    public function winnerPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'winner_photo_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * The admin-configurable weekly schedule: day-of-week (0=Sunday..6=
     * Saturday, matching both Carbon::dayOfWeek and cron's day-of-week
     * field) and "H:i" time, for when the competition starts and ends.
     * Defaults to the original fixed Monday 00:00 -> Friday 20:00 window.
     */
    public static function schedule(): array
    {
        $settings = Setting::query()
            ->whereIn('key', [
                'photo_competition_start_day',
                'photo_competition_start_time',
                'photo_competition_end_day',
                'photo_competition_end_time',
            ])
            ->get()
            ->pluck('value', 'key');

        return [
            'startDay' => (int) ($settings['photo_competition_start_day'] ?? 1),
            'startTime' => $settings['photo_competition_start_time'] ?? '00:00',
            'endDay' => (int) ($settings['photo_competition_end_day'] ?? 5),
            'endTime' => $settings['photo_competition_end_time'] ?? '20:00',
        ];
    }

    /**
     * The exact starts_at/ends_at instants for the week containing $anchor,
     * derived from schedule(). Anchored to a fixed Sunday-starting week (not
     * the app's configured week start) so the day-of-week offsets above are
     * unambiguous.
     */
    public static function scheduledWindowFor(\Carbon\CarbonInterface $anchor): array
    {
        $schedule = static::schedule();
        $weekStart = $anchor->copy()->startOfWeek(Carbon::SUNDAY);

        return [
            $weekStart->copy()->addDays($schedule['startDay'])->setTimeFromTimeString($schedule['startTime']),
            $weekStart->copy()->addDays($schedule['endDay'])->setTimeFromTimeString($schedule['endTime']),
        ];
    }

    /**
     * Whether "now" matches the configured start or end moment — used by
     * the scheduler (routes/console.php) to decide whether to fire the
     * start/end commands this minute, without hardcoding the day/time.
     */
    public static function matchesScheduledMoment(string $which): bool
    {
        $schedule = static::schedule();
        $now = now();

        return $now->dayOfWeek === $schedule["{$which}Day"]
            && $now->format('H:i') === $schedule["{$which}Time"];
    }
}
