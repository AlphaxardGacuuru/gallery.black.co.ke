<?php

namespace Database\Seeders;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReferralSeeder extends Seeder
{
    private const REFERRER_EMAIL = 'alphaxardgacuuru47@gmail.com';

    /**
     * Tops up alphaxardgacuuru47@gmail.com to 10 referrals, each against a
     * freshly created referred user — enough to clear two payout batches at
     * the default referral_threshold of 5 (see ReferralController::settings).
     * Idempotent: re-running only creates however many are still missing.
     */
    public function run(): void
    {
        $referrer = User::where('email', self::REFERRER_EMAIL)->first();

        if (! $referrer) {
            $this->command?->warn(self::REFERRER_EMAIL . ' not found — run UserSeeder first.');

            return;
        }

        $existing = Referral::where('referrer_id', $referrer->id)->count();
        $missing = 10 - $existing;

        if ($missing <= 0) {
            $this->command?->info(self::REFERRER_EMAIL . " already has {$existing} referrals — skipping.");

            return;
        }

        User::factory()
            ->count($missing)
            ->create()
            ->each(fn (User $referred) => Referral::record($referrer->id, $referred));
    }
}
