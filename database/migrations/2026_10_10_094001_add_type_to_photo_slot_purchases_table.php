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
            $table->string('type')->default('extra_entry')->after('competition_id');
            $table->index(['user_id', 'competition_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_slot_purchases', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'competition_id', 'type', 'status']);
            $table->dropColumn('type');
        });
    }
};
