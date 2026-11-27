<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // user_events counted per Nairobi day, filled nightly by
        // app:summarize-user-events. Fast to query and kept after raw page
        // views are pruned.
        Schema::create('user_event_daily_totals', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            // An event name, or "*" for everyone active that day.
            $table->string('event');
            // Empty rather than null so the unique index holds.
            $table->string('source')->default('');
            // The page pattern, for page.viewed.
            $table->string('route')->default('');
            $table->unsignedInteger('events');
            // Distinct signed-in users, and distinct guest browsers.
            $table->unsignedInteger('users');
            $table->unsignedInteger('guests');
            $table->timestamps();

            $table->unique(['date', 'event', 'source', 'route']);
            $table->index(['event', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_event_daily_totals');
    }
};
