<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set by an admin for suspected abuse (e.g. like farming). A
            // flagged user's likes don't count and they can't be paid.
            $table->timestamp('flagged_at')->nullable()->after('verified');
            // Admin-only note on why, never shown to the user.
            $table->string('flag_reason')->nullable()->after('flagged_at');
            // When the flagged user disputed it by updating their phone
            // number, for an admin to review.
            $table->timestamp('flag_refuted_at')->nullable()->after('flag_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['flagged_at', 'flag_reason', 'flag_refuted_at']);
        });
    }
};
