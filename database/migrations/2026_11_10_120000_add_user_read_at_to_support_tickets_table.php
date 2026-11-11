<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            // When the customer last opened the conversation, so replies
            // after it show as unread on the Feedback button.
            $table->timestamp('user_read_at')
                ->nullable()
                ->after('status');
        });

        // Existing conversations count as read, or every past reply would
        // light up the badge the moment this ships.
        DB::table('support_tickets')
            ->update(['user_read_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn('user_read_at');
        });
    }
};
