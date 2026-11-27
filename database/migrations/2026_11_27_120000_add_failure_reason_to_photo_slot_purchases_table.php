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
        Schema::table('photo_slot_purchases', function (Blueprint $table) {
            // Kopokopo's own words for a failed prompt, e.g. "Request
            // Cancelled by user.", so the buyer can be told why.
            $table->string('failure_reason')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_slot_purchases', function (Blueprint $table) {
            $table->dropColumn('failure_reason');
        });
    }
};
