<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // The one-off bonus a referrer earns once the person they
            // referred posts a photo. Tracked separately from the batch
            // reward (amount_paid/kopokopo_reference/paid_at), since the two
            // are earned and paid independently.
            $table->decimal('photo_bonus_amount', 10, 2)->nullable()->after('kopokopo_reference');
            $table->string('photo_bonus_reference')->nullable()->index()->after('photo_bonus_amount');
            $table->timestamp('photo_bonus_paid_at')->nullable()->after('photo_bonus_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropIndex(['photo_bonus_reference']);
            $table->dropColumn(['photo_bonus_amount', 'photo_bonus_reference', 'photo_bonus_paid_at']);
        });
    }
};
