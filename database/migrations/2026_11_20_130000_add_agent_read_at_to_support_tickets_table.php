<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            // When someone on the support team last opened the conversation,
            // so the customer sees "Seen" under their messages.
            $table->timestamp('agent_read_at')
                ->nullable()
                ->after('user_read_at');
        });

        // No backfill: existing tickets stay unseen rather than showing a
        // read receipt for a read that may never have happened.
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn('agent_read_at');
        });
    }
};
