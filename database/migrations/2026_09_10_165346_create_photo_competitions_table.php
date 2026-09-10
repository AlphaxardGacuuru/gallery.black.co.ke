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
        Schema::create('photo_competitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('active');
            $table->unsignedInteger('prize_amount');
            // No FK constraint here: photos.competition_id references this
            // table, so this column's FK (added once photos exists) is
            // defined in the photo_likes migration to avoid a create-order
            // cycle between these two tables.
            $table->uuid('winner_photo_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_competitions');
    }
};
