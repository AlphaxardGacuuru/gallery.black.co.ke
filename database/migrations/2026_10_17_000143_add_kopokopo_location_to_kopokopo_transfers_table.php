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
        Schema::table('kopokopo_transfers', function (Blueprint $table) {
            $table->text('kopokopo_location')->nullable()->after('kopokopo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kopokopo_transfers', function (Blueprint $table) {
            $table->dropColumn('kopokopo_location');
        });
    }
};
