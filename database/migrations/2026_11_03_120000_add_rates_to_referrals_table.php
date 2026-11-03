<?php

use App\Http\Services\ReferralService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // What this referral is worth, fixed when it was made, so a
            // later price change doesn't re-price referrals already earned.
            // reward_rate is this referral's share of a batch reward
            // (reward / threshold at the time).
            $table->decimal('reward_rate', 10, 2)->nullable()->after('referred_id');
            $table->decimal('photo_bonus_rate', 10, 2)->nullable()->after('reward_rate');
        });

        // Older referrals didn't record the price they were made at. Paid or
        // in-flight ones keep what they were paid; the rest get today's
        // price, the closest thing on record.
        ['threshold' => $threshold, 'rewardAmount' => $rewardAmount, 'photoBonusAmount' => $photoBonusAmount]
            = app(ReferralService::class)->settings();
        $currentRate = $threshold > 0 ? round($rewardAmount / $threshold, 2) : 0;

        DB::table('referrals')->whereNotNull('amount_paid')->update(['reward_rate' => DB::raw('amount_paid')]);
        DB::table('referrals')->whereNull('reward_rate')->update(['reward_rate' => $currentRate]);

        DB::table('referrals')->whereNotNull('photo_bonus_amount')->update(['photo_bonus_rate' => DB::raw('photo_bonus_amount')]);
        DB::table('referrals')->whereNull('photo_bonus_rate')->update(['photo_bonus_rate' => $photoBonusAmount]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['reward_rate', 'photo_bonus_rate']);
        });
    }
};
