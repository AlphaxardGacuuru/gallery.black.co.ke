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
            $table->string('status')->nullable()->after('currency');
            $table->json('errors')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kopokopo_transfers', function (Blueprint $table) {
            $table->dropColumn(['status', 'errors']);
        });
    }
};
