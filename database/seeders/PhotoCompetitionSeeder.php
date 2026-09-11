<?php

namespace Database\Seeders;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoCompetitionSeeder extends Seeder
{
    /**
     * Background / horizon-band / accent triples, so generated thumbnails
     * read as photos rather than flat colour swatches.
     */
    private const PALETTES = [
        [[255, 183, 94], [255, 94, 77], [255, 244, 214]],   // sunset
        [[86, 168, 255], [255, 255, 255], [214, 240, 255]], // sky
        [[46, 125, 90], [23, 66, 48], [198, 224, 180]],     // forest
        [[38, 45, 74], [16, 20, 38], [255, 214, 102]],      // night + moon
        [[224, 122, 95], [129, 178, 154], [244, 241, 222]], // terracotta
        [[27, 27, 38], [212, 175, 55], [58, 58, 74]],       // gold on charcoal
    ];

    private const CAPTIONS = [
        'Golden hour over the valley',
        'Matatu graffiti, downtown',
        'Fog rolling over the hills',
        'Street portraits, CBD',
        'Sunday market colours',
        'Rooftops at dusk',
        'Rain on Tom Mboya Street',
        'Lake view from the ridge',
        'Backlit acacia',
        'Old town doorways',
        null,
        null,
        null,
    ];

    private const DIMENSIONS = [
        [1080, 1080],
        [1080, 1350],
        [1350, 1080],
        [1080, 1620],
    ];

    private Collection $userPool;

    public function run(): void
    {
        // Known dev/demo accounts (if seeded) join the pool so their own
        // submissions and likes show up when browsing as them.
        $knownUsers = User::whereIn('email', [
            'al@property.black.co.ke',
            'alphaxardgacuuru47@gmail.com',
            'gacuuruwakarenge@gmail.com',
            'cikumuhandi@gmail.com',
        ])->get();

        $this->userPool = $knownUsers->merge(User::factory()->count(45)->create());

        $this->seedCurrentCompetition();
        $this->seedPastCompetitions();
    }

    /**
     * This week's still-open competition, with a few entries already in —
     * some freshly submitted with no likes yet, others with a head start.
     */
    private function seedCurrentCompetition(): void
    {
        $startsAt = now()->startOfWeek();

        // Reuse an already-active competition (e.g. from earlier manual
        // testing) rather than creating a second one — the app assumes a
        // single active competition at a time.
        $competition = PhotoCompetition::active()->first() ?? PhotoCompetition::create([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays(4)->setTime(20, 0),
            'status' => PhotoCompetition::STATUS_ACTIVE,
            'prize_amount' => 500,
        ]);

        $alreadyEntered = $competition->photos()->pluck('user_id');

        $rangeEnd = now()->lessThan($competition->ends_at) ? now() : $competition->ends_at;

        $this->userPool
            ->reject(fn (User $user) => $alreadyEntered->contains($user->id))
            ->shuffle()
            ->take(9)
            ->values()
            ->each(function (User $user, int $index) use ($competition, $startsAt, $rangeEnd) {
                $submittedAt = $startsAt->copy()->addSeconds(random_int(0, max(1, $startsAt->diffInSeconds($rangeEnd))));

                // The first couple of entries look like they just landed.
                $likes = $index < 2 ? 0 : random_int(1, 38);

                $this->createPhoto($competition, $user, $submittedAt, $likes);
            });
    }

    /**
     * A handful of ended competitions, each with a winner, so the discover
     * grid has real pagination and a mix of like counts to sort through.
     */
    private function seedPastCompetitions(): void
    {
        $thisWeekStart = now()->startOfWeek();

        foreach (range(1, 5) as $weeksAgo) {
            $startsAt = $thisWeekStart->copy()->subWeeks($weeksAgo);
            $endsAt = $startsAt->copy()->addDays(4)->setTime(20, 0);

            $competition = PhotoCompetition::create([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => PhotoCompetition::STATUS_ENDED,
                'prize_amount' => collect([500, 750, 1000])->random(),
            ]);

            $photos = $this->userPool->shuffle()->take(random_int(8, 12))->values()->map(
                function (User $user) use ($competition, $startsAt, $endsAt) {
                    $submittedAt = $startsAt->copy()->addSeconds(random_int(0, $startsAt->diffInSeconds($endsAt)));

                    return $this->createPhoto($competition, $user, $submittedAt, random_int(0, 60));
                }
            );

            $winner = $photos->sortByDesc('likes_count')->first();
            $competition->update(['winner_photo_id' => $winner->id]);
        }
    }

    private function createPhoto(PhotoCompetition $competition, User $user, CarbonInterface $createdAt, int $likes): Photo
    {
        [$width, $height] = collect(self::DIMENSIONS)->random();

        $photo = Photo::create([
            'competition_id' => $competition->id,
            'user_id' => $user->id,
            'disk' => 'public',
            'path' => $this->generateImage($width, $height),
            'caption' => collect(self::CAPTIONS)->random(),
            'width' => $width,
            'height' => $height,
            'likes_count' => $likes,
        ]);

        $photo->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        $this->createLikes($photo, $likes);

        return $photo;
    }

    private function createLikes(Photo $photo, int $count): void
    {
        if ($count === 0) {
            return;
        }

        $this->userPool
            ->reject(fn (User $user) => $user->id === $photo->user_id)
            ->shuffle()
            ->take($count)
            ->each(fn (User $liker) => $photo->likes()->create(['user_id' => $liker->id]));
    }

    /**
     * A small generated "landscape" (horizon band + sun/moon disc) instead
     * of a network fetch, so seeding works offline and every photo still
     * looks distinct in the masonry grid.
     */
    private function generateImage(int $width, int $height): string
    {
        [$bg, $accent, $highlight] = collect(self::PALETTES)->random();

        $image = imagecreatetruecolor($width, $height);

        imagefill($image, 0, 0, imagecolorallocate($image, ...$bg));

        imagefilledrectangle(
            $image,
            0,
            (int) ($height * 0.66),
            $width,
            $height,
            imagecolorallocate($image, ...$accent)
        );

        $discRadius = (int) ($width * 0.18);
        imagefilledellipse(
            $image,
            random_int((int) ($width * 0.25), (int) ($width * 0.75)),
            (int) ($height * 0.5),
            $discRadius,
            $discRadius,
            imagecolorallocate($image, ...$highlight)
        );

        $path = 'photos/' . Str::uuid() . '.jpg';

        ob_start();
        imagejpeg($image, null, 82);
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }
}
