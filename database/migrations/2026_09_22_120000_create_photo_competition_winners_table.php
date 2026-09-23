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
        Schema::create('photo_competition_winners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('competition_id')
                ->constrained('photo_competitions')
                ->cascadeOnDelete();
            $table->foreignUuid('photo_id')
                ->nullable()
                ->constrained('photos')
                ->nullOnDelete();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->unsignedInteger('prize_amount');
            $table->timestamp('prize_paid_at')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'position']);
            $table->index(['competition_id', 'photo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_competition_winners');
    }
};
