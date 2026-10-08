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
        Schema::table('photo_competition_winners', function (Blueprint $table) {
            $table->string('kopokopo_reference')->nullable()->after('prize_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_competition_winners', function (Blueprint $table) {
            $table->dropColumn('kopokopo_reference');
        });
    }
};
