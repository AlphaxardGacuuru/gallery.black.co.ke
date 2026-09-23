<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Carries the single-winner era's data forward: each already-ended
     * competition's winner_photo_id becomes a position-1 row (preserving
     * whatever prize_amount/prize_paid_at it already had), and the new
     * global tiers setting is seeded from whatever prize amount admins had
     * already configured — never a hardcoded default — so nobody's
     * configured prize silently drops once prize_amount is a global tiers
     * array instead of a per-competition column.
     */
    public function up(): void
    {
        $now = now();

        $competitions = DB::table('photo_competitions')
            ->whereNotNull('winner_photo_id')
            ->select('id', 'winner_photo_id', 'prize_amount', 'prize_paid_at')
            ->get();

        $rows = $competitions
            ->map(function ($competition) use ($now) {
                $photo = DB::table('photos')
                    ->where('id', $competition->winner_photo_id)
                    ->first(['id', 'user_id']);

                if (! $photo) {
                    return null;
                }

                return [
                    'id' => (string) Str::uuid(),
                    'competition_id' => $competition->id,
                    'photo_id' => $photo->id,
                    'user_id' => $photo->user_id,
                    'position' => 1,
                    'prize_amount' => $competition->prize_amount,
                    'prize_paid_at' => $competition->prize_paid_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('photo_competition_winners')->insert($rows);
        }

        $existingPrizeAmount = DB::table('settings')->where('key', 'photo_prize_amount')->value('value');
        $position1Amount = $existingPrizeAmount !== null ? (int) json_decode($existingPrizeAmount, true) : 500;

        DB::table('settings')->updateOrInsert(
            ['key' => 'photo_prize_tiers'],
            [
                'value' => json_encode(array_pad([$position1Amount], 10, 0)),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * Schema-only reversal: removes the rows/setting this migration created,
     * but the original per-competition prize_amount/prize_paid_at values
     * this read from are untouched (they still live on photo_competitions
     * until the later drop-columns migration), so nothing here is lossy.
     */
    public function down(): void
    {
        $competitionIds = DB::table('photo_competitions')
            ->whereNotNull('winner_photo_id')
            ->pluck('id');

        DB::table('photo_competition_winners')
            ->whereIn('competition_id', $competitionIds)
            ->where('position', 1)
            ->delete();

        DB::table('settings')->where('key', 'photo_prize_tiers')->delete();
    }
};
