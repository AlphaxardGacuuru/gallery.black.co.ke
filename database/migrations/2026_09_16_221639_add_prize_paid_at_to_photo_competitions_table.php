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
        Schema::table('photo_competitions', function (Blueprint $table) {
            $table->timestamp('prize_paid_at')->nullable()->after('winner_photo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_competitions', function (Blueprint $table) {
            $table->dropColumn('prize_paid_at');
        });
    }
};
