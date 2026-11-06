<?php

namespace App\Console\Commands;

use App\Http\Services\UnsplashService;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoImage;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\SeedUserSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Signature('app:post-seed-photos')]
#[Description('Posts the next Unsplash photo from each of this competition\'s seed posters, one a day each for its first three days.')]
class PostSeedPhotos extends Command
{
    /** How many seed users post, see posters(). */
    public const POSTERS = 2;

    /** Photos each poster posts per competition. */
    public const PHOTOS_PER_COMPETITION = 3;

    /** Each seed post is a carousel: a cover plus extra images. */
    public const IMAGES_PER_POST = 2;

    /**
     * Runs every few minutes (routes/console.php) and posts at most one
     * photo per run, from the first poster (in posters() order) with one
     * due, so it's safe to re-run and catches up after downtime.
     */
    public function handle(UnsplashService $unsplash): int
    {
        $competition = PhotoCompetition::active()->first();

        if (! $competition) {
            return self::SUCCESS;
        }

        $posters = static::posters();

        if ($posters->isEmpty()) {
            $this->components->warn('No seed users exist. Run: php artisan db:seed --class=SeedUserSeeder');

            return self::SUCCESS;
        }

        // first() stops at the first poster that actually posts.
        $posters->first(fn(User $user, int $position) => $this->postNextPhoto($unsplash, $competition, $user, $position));

        return self::SUCCESS;
    }

    /**
     * The seed users who post every competition's photos: the first
     * POSTERS names in SeedUserSeeder::NAMES, in that order, so raising or
     * lowering POSTERS only adds or drops users from the end and the same
     * profiles keep building up a history.
     *
     * @return Collection<int, User>
     */
    public static function posters(): Collection
    {
        $emails = collect(SeedUserSeeder::NAMES)
            ->take(self::POSTERS)
            ->map(fn(array $entry) => SeedUserSeeder::email($entry[0]))
            ->values();

        return User::query()
            ->where('is_seed', true)
            ->whereIn('email', $emails)
            ->get()
            ->sortBy(fn(User $user) => $emails->search($user->email))
            ->values();
    }

    /**
     * Post $user's next photo in this competition if it's due. Returns
     * whether it posted.
     */
    private function postNextPhoto(UnsplashService $unsplash, PhotoCompetition $competition, User $user, int $position): bool
    {
        $posted = $competition->photos()
            ->where('user_id', $user->id)
            ->count();

        if ($posted >= self::PHOTOS_PER_COMPETITION) {
            return false;
        }

        $dueAt = static::dueAt($competition, $posted, $position);

        if (now()->lessThan($dueAt) || $dueAt->greaterThanOrEqualTo($competition->ends_at)) {
            return false;
        }

        $unsplashPhotos = $unsplash->randomUnusedPhotos(self::IMAGES_PER_POST);
        [$cover, $extras] = [$unsplashPhotos[0], array_slice($unsplashPhotos, 1)];

        $photo = Photo::create([
            'competition_id' => $competition->id,
            'user_id' => $user->id,
            'disk' => Photo::DISK_REMOTE,
            'path' => static::sizedUrl($cover['urls']['raw'], 2160, 80),
            'thumbnail_path' => static::sizedUrl($cover['urls']['raw'], 1080, 75),
            'caption' => static::caption(...$unsplashPhotos),
            'unsplash_id' => $cover['id'],
            'width' => $cover['width'],
            'height' => $cover['height'],
        ]);

        collect($extras)->each(fn(array $extra, int $index) => PhotoImage::create([
            'photo_id' => $photo->id,
            'disk' => Photo::DISK_REMOTE,
            'path' => static::sizedUrl($extra['urls']['raw'], 2160, 80),
            'thumbnail_path' => static::sizedUrl($extra['urls']['raw'], 1080, 75),
            'unsplash_id' => $extra['id'],
            'width' => $extra['width'],
            'height' => $extra['height'],
            'position' => $index + 1,
        ]));

        // The photos are already live, so a failed download ping shouldn't
        // fail the run (and make the next run post a duplicate).
        collect($unsplashPhotos)->each(fn(array $unsplashPhoto) => rescue(fn() => $unsplash->trackDownload($unsplashPhoto)));

        $this->components->info(sprintf(
            '%s posted seed photo %s (%s).',
            $user->name,
            $photo->id,
            collect($unsplashPhotos)->pluck('id')->join(', '),
        ));

        return true;
    }

    /**
     * When the photo at this index (0, 1, 2) is due for the poster at this
     * position: that many days after the competition starts, plus a 1 to
     * 12 hour offset that varies per competition, photo and poster, so
     * posts don't land at the same minute every week or all at once.
     */
    public static function dueAt(PhotoCompetition $competition, int $index, int $position = 0): CarbonInterface
    {
        // The first poster keeps the key it had when there was only one.
        $key = $position === 0
            ? "{$competition->id}:{$index}"
            : "{$competition->id}:{$index}:{$position}";
        $offsetMinutes = 60 + crc32($key) % (11 * 60);

        return $competition->starts_at
            ->copy()
            ->addDays($index)
            ->addMinutes($offsetMinutes);
    }

    /**
     * The cover's own description (when it has one) followed by the credit
     * for every photographer in the post, kept within the 255-character
     * caption column without ever truncating the credit.
     *
     * @param  array<string, mixed>  ...$unsplashPhotos  The cover first
     */
    public static function caption(array ...$unsplashPhotos): string
    {
        $credit = UnsplashService::credit(...$unsplashPhotos);
        $cover = $unsplashPhotos[0];
        $description = trim((string) ($cover['description'] ?? $cover['alt_description'] ?? ''));

        if ($description === '') {
            return $credit;
        }

        $room = 255 - mb_strlen($credit) - 2;
        $description = Str::limit(rtrim(Str::ucfirst($description), '.'), $room - 3, '...');

        return $description . (str_ends_with($description, '...') ? ' ' : '. ') . $credit;
    }

    /**
     * Unsplash's "raw" URL accepts imgix sizing params, used here to match
     * the sizes GeneratePhotoThumbnailJob produces for regular uploads.
     */
    private static function sizedUrl(string $rawUrl, int $width, int $quality): string
    {
        $separator = str_contains($rawUrl, '?') ? '&' : '?';

        return $rawUrl . $separator . http_build_query([
            'w' => $width,
            'q' => $quality,
            'fm' => 'jpg',
            'fit' => 'max',
        ]);
    }
}
