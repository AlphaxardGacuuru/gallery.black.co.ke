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
        Schema::create('photo_likes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('photo_id')
                ->constrained('photos')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['photo_id', 'user_id']);
        });

        Schema::table('photo_competitions', function (Blueprint $table) {
            $table->foreign('winner_photo_id')
                ->references('id')->on('photos')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_competitions', function (Blueprint $table) {
            $table->dropForeign(['winner_photo_id']);
        });

        Schema::dropIfExists('photo_likes');
    }
};
