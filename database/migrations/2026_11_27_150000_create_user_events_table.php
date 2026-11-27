<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What users do in the app, for usage analytics. The events are
        // listed in App\Enums\UserEventName. Rows are never updated.
        Schema::create('user_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Kept, without the person, when an account is deleted.
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('event');
            $table->foreignUuid('photo_id')
                ->nullable()
                ->constrained('photos')
                ->nullOnDelete();
            $table->uuid('competition_id')->nullable();
            // Where it happened, e.g. app or push_action.
            $table->string('source')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at');

            $table->index(['event', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_events');
    }
};
