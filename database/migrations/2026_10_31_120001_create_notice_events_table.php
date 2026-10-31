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
        Schema::create('notice_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('notice_id')
                ->constrained('notices')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('version', 16);
            $table->string('action');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'action', 'created_at']);
            $table->index(['notice_id', 'user_id', 'version']);
            $table->index(['notice_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notice_events');
    }
};
