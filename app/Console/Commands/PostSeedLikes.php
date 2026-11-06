<?php

namespace App\Console\Commands;

use App\Events\PhotoLikedEvent;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoLike;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:post-seed-likes')]
#[Description('Has seed users like the active competition\'s photos, spread over the days it runs.')]
class PostSeedLikes extends Command
{
    /** Seed likes every real user's photo gets over a competition. */
    public const LIKES_PER_PHOTO = 5;

    /** Seed likes a seed user's own photo gets over a competition. */
    public const LIKES_PER_SEED_PHOTO = 15;

    /**
     * Runs every few minutes (routes/console.php) and gives each photo the
     * seed likes that have fallen due since the last run, so it's safe to
     * re-run and catches up after downtime.
     */
    public function handle(): int
    {
        $competition = PhotoCompetition::active()->first();

        if (! $competition) {
            return self::SUCCESS;
        }

        $given = $competition->photos()
            ->with('user')
            ->withCount(['likes as seed_likes_count' => fn($query) => $query->whereHas('user', fn($query) => $query->where('is_seed', true))])
            ->get()
            ->sum(fn(Photo $photo) => $this->likeDue($competition, $photo));

        if ($given > 0) {
            $this->components->info("Seed users gave {$given} likes.");
        }

        return self::SUCCESS;
    }

    /**
     * Add whichever of this photo's seed likes are due and not given yet,
     * from seed users in a random order who haven't liked it already.
     * Returns how many were added.
     */
    private function likeDue(PhotoCompetition $competition, Photo $photo): int
    {
        $due = collect(range(0, static::targetFor($photo) - 1))
            ->filter(fn(int $index) => now()->greaterThanOrEqualTo(static::dueAt($competition, $photo, $index)))
            ->count();

        $missing = $due - $photo->seed_likes_count;

        if ($missing <= 0) {
            return 0;
        }

        return User::query()
            ->where('is_seed', true)
            ->unflagged()
            ->whereKeyNot($photo->user_id)
            ->whereNotIn(
                'id',
                PhotoLike::query()
                    ->select('user_id')
                    ->where('photo_id', $photo->id)
            )
            ->inRandomOrder()
            ->limit($missing)
            ->get()
            ->each(fn(User $user) => $this->like($photo, $user))
            ->count();
    }

    /**
     * Same effect as a real like: counted, broadcast live, and the owner is
     * notified (PhotoLikedListener skips seed owners).
     */
    private function like(Photo $photo, User $user): void
    {
        $photo->likes()
            ->create(['user_id' => $user->id]);
        $photo->increment('likes_count');

        broadcast(new PhotoLikedEvent($photo, $user));
    }

    public static function targetFor(Photo $photo): int
    {
        return $photo->user?->is_seed
            ? self::LIKES_PER_SEED_PHOTO
            : self::LIKES_PER_PHOTO;
    }

    /**
     * When the photo's like at this index is due. The time from when the
     * photo was posted (or the competition started, if later) until the
     * competition ends is split into one equal slot per like, and each like
     * lands at a point in its slot that varies per photo, so likes trickle
     * in over the days instead of arriving in a burst.
     */
    public static function dueAt(PhotoCompetition $competition, Photo $photo, int $index): CarbonInterface
    {
        $start = $photo->created_at->max($competition->starts_at);
        $window = max(0, $start->diffInSeconds($competition->ends_at));
        $slot = $window / static::targetFor($photo);
        $offset = (crc32("{$photo->id}:{$index}") % 1000) / 1000;

        return $start
            ->copy()
            ->addSeconds((int) floor($slot * ($index + $offset)));
    }
}
