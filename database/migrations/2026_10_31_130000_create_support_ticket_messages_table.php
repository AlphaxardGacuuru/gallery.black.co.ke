<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('support_ticket_id')
                ->constrained()
                ->cascadeOnDelete();
            // The customer or the admin who wrote it; null for the assistant.
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('author'); // user, assistant, agent
            $table->text('body');
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
    }
};
