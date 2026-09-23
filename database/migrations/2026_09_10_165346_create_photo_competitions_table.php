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
            $table->uuid('winner_photo_id')->nullable();
            $table->timestamp('prize_paid_at')->nullable();
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
