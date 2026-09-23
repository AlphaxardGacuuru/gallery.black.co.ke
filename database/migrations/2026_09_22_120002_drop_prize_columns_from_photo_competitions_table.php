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
            if (Schema::hasColumn('photo_competitions', 'winner_photo_id')) {
                $table->dropForeign(['winner_photo_id']);
                $table->dropColumn('winner_photo_id');
            }

            if (Schema::hasColumn('photo_competitions', 'prize_amount')) {
                $table->dropColumn('prize_amount');
            }

            if (Schema::hasColumn('photo_competitions', 'prize_paid_at')) {
                $table->dropColumn('prize_paid_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * Restores the columns' shape only — the data they held is gone (it was
     * already carried into photo_competition_winners by the prior
     * migration before this one ran).
     */
    public function down(): void
    {
        Schema::table('photo_competitions', function (Blueprint $table) {
            if (! Schema::hasColumn('photo_competitions', 'prize_amount')) {
                $table->unsignedInteger('prize_amount')->default(0);
            }

            if (! Schema::hasColumn('photo_competitions', 'winner_photo_id')) {
                $table->uuid('winner_photo_id')->nullable();
                $table->foreign('winner_photo_id')->references('id')->on('photos')->nullOnDelete();
            }

            if (! Schema::hasColumn('photo_competitions', 'prize_paid_at')) {
                $table->timestamp('prize_paid_at')->nullable();
            }
        });
    }
};
