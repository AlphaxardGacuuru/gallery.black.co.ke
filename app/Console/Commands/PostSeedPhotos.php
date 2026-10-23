<?php

namespace App\Console\Commands;

use App\Http\Services\UnsplashService;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:post-seed-photos')]
#[Description('Posts the next Unsplash photo from this competition\'s seed user, one a day for its first three days.')]
class PostSeedPhotos extends Command
{
    public const PHOTOS_PER_COMPETITION = 3;

    /**
     * Runs every few minutes (routes/console.php) and posts at most one
     * photo per run, so it's safe to re-run and catches up after downtime.
     */
    public function handle(UnsplashService $unsplash): int
    {
        $competition = PhotoCompetition::active()->first();

        if (! $competition) {
            return self::SUCCESS;
        }

        $seedPhotos = $competition->photos()
            ->whereHas('user', fn($query) => $query->where('is_seed', true))
            ->with('user')
            ->orderBy('created_at')
            ->get();

        if ($seedPhotos->count() >= self::PHOTOS_PER_COMPETITION) {
            return self::SUCCESS;
        }

        $dueAt = static::dueAt($competition, $seedPhotos->count());

        if (now()->lessThan($dueAt) || $dueAt->greaterThanOrEqualTo($competition->ends_at)) {
            return self::SUCCESS;
        }

        // One seed user per competition: whoever posted the first photo
        // keeps posting the rest.
        $user = $seedPhotos->first()?->user ?? static::nextSeedUser();

        if (! $user) {
            $this->components->warn('No seed users exist. Run: php artisan db:seed --class=SeedUserSeeder');

            return self::SUCCESS;
        }

        $unsplashPhoto = $unsplash->randomUnusedPhoto();

        $photo = Photo::create([
            'competition_id' => $competition->id,
            'user_id' => $user->id,
            'disk' => Photo::DISK_REMOTE,
            'path' => static::sizedUrl($unsplashPhoto['urls']['raw'], 2160, 80),
            'thumbnail_path' => static::sizedUrl($unsplashPhoto['urls']['raw'], 1080, 75),
            'caption' => static::caption($unsplashPhoto),
            'unsplash_id' => $unsplashPhoto['id'],
            'width' => $unsplashPhoto['width'],
            'height' => $unsplashPhoto['height'],
        ]);

        // The photo is already live, so a failed download ping shouldn't
        // fail the run (and make the next run post a duplicate).
        rescue(fn() => $unsplash->trackDownload($unsplashPhoto));

        $this->components->info("{$user->name} posted seed photo {$photo->id} ({$unsplashPhoto['id']}).");

        return self::SUCCESS;
    }

    /**
     * When the photo at this index (0, 1, 2) is due: that many days after
     * the competition starts, plus a 1 to 12 hour offset that varies per
     * competition and photo, so posts don't land at the same minute every
     * week.
     */
    public static function dueAt(PhotoCompetition $competition, int $index): CarbonInterface
    {
        $offsetMinutes = 60 + crc32("{$competition->id}:{$index}") % (11 * 60);

        return $competition->starts_at
            ->copy()
            ->addDays($index)
            ->addMinutes($offsetMinutes);
    }

    /**
     * The seed user who has gone longest without posting (never-posted
     * ones first, picked at random among ties), so the pool rotates.
     */
    public static function nextSeedUser(): ?User
    {
        return User::query()
            ->where('is_seed', true)
            ->withMax('photos', 'created_at')
            ->orderByRaw('photos_max_created_at IS NOT NULL')
            ->orderBy('photos_max_created_at')
            ->inRandomOrder()
            ->first();
    }

    /**
     * The photo's own description (when it has one) followed by the
     * photographer credit, kept within the 255-character caption column
     * without ever truncating the credit.
     *
     * @param  array<string, mixed>  $unsplashPhoto
     */
    public static function caption(array $unsplashPhoto): string
    {
        $credit = UnsplashService::credit($unsplashPhoto);
        $description = trim((string) ($unsplashPhoto['description'] ?? $unsplashPhoto['alt_description'] ?? ''));

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
