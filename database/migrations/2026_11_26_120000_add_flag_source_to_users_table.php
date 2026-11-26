<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Who set the flag: an admin, or the sign-up screening agent.
            // Adding a phone number lifts a screening flag by itself.
            $table->string('flag_source')->nullable()->after('flag_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('flag_source');
        });
    }
};
