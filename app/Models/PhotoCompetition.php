<?php

namespace App\Models;

use App\Http\Services\KopokopoTransferService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'reminder_sent_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'competition_id');
    }

    public function winners(): HasMany
    {
        return $this->hasMany(PhotoCompetitionWinner::class, 'competition_id')->orderBy('position');
    }

    /**
     * What the Kopokopo wallet needs to pay this competition's outstanding
     * prizes: winners not yet paid or in flight, skipping seed accounts
     * (marked paid automatically) and anyone without a phone number (who
     * can't be paid). Expects winners.user to be loaded.
     *
     * @return array{payouts: int, prizes: int, fees: int, total: int}
     */
    public function payoutTotals(): array
    {
        $payable = $this->winners->filter(fn(PhotoCompetitionWinner $winner) => ! $winner->prize_paid_at
            && ! $winner->kopokopo_reference
            && $winner->user
            && ! $winner->user->is_seed
            && $winner->user->phone);

        $prizes = (int) $payable->sum('prize_amount');
        $fees = $payable->count() * KopokopoTransferService::TRANSFER_FEE;

        return [
            'payouts' => $payable->count(),
            'prizes' => $prizes,
            'fees' => $fees,
            'total' => $prizes + $fees,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Halfway between starts_at and ends_at, when the mid-challenge reminder
     * (SendPhotoCompetitionReminder) goes out.
     */
    public function midpoint(): \Carbon\CarbonInterface
    {
        $durationInSeconds = $this->starts_at->diffInSeconds($this->ends_at);

        return $this->starts_at
            ->copy()
            ->addSeconds((int) ($durationInSeconds / 2));
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
     * The KES prize for each of the top 10 positions, index 0 = position 1.
     * A position with a prize of 0 (the default beyond position 1) means
     * that rank isn't paid — EndPhotoCompetition stops ranking further once
     * it hits one, so only positions with a real prize get a winner row.
     */
    public static function prizeTiers(): array
    {
        $tiers = Setting::query()->where('key', 'photo_prize_tiers')->value('value') ?? [500];

        return collect($tiers)->take(10)->pad(10, 0)->all();
    }

    /**
     * One line per paid position, e.g. "1st place: KES 500" — stops at the
     * first unpaid tier, same convention EndPhotoCompetition ranks against.
     *
     * @return array<int, string>
     */
    public static function prizeTierLines(): array
    {
        $ordinals = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];

        return collect(static::prizeTiers())
            ->takeWhile(fn(int $amount) => $amount > 0)
            ->map(fn(int $amount, int $index) => $ordinals[$index] . ' place: KES ' . $amount)
            ->all();
    }

    /**
     * Whether users can buy an extra photo-submission slot, and at what KES
     * price (a single JSON-object Setting rather than two rows, since both
     * values are always read/written together).
     *
     * @return array{enabled: bool, price: int}
     */
    public static function extraSlotSettings(): array
    {
        $value = Setting::query()
            ->where('key', 'photo_extra_slot')
            ->value('value') ?? [];

        return [
            'enabled' => (bool) ($value['enabled'] ?? false),
            'price' => (int) ($value['price'] ?? 0),
        ];
    }

    /**
     * Whether users can buy additional photo slots on a single entry (see
     * Photo::maxImagesFor()), and the KES price per slot.
     *
     * @return array{enabled: bool, price: int}
     */
    public static function extraPhotoSettings(): array
    {
        $value = Setting::query()
            ->where('key', 'photo_extra_photo')
            ->value('value') ?? [];

        return [
            'enabled' => (bool) ($value['enabled'] ?? false),
            'price' => (int) ($value['price'] ?? 0),
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
            $weekStart
                ->copy()
                ->addDays($schedule['startDay'])
                ->setTimeFromTimeString($schedule['startTime']),

            $weekStart
                ->copy()
                ->addDays($schedule['endDay'])
                ->setTimeFromTimeString($schedule['endTime']),
        ];
    }

    /**
     * The next moment a competition is scheduled to start, strictly after
     * now — this week's configured start time if it hasn't happened yet,
     * otherwise next week's.
     */
    public static function nextScheduledStart(): \Carbon\CarbonInterface
    {
        $now = now();
        [$startsAt] = static::scheduledWindowFor($now);

        if ($startsAt->lessThanOrEqualTo($now)) {
            [$startsAt] = static::scheduledWindowFor($now->copy()->addWeek());
        }

        return $startsAt;
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
