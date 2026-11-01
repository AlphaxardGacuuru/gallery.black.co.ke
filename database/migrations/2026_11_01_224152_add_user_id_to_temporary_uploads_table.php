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
        Schema::table('temporary_uploads', function (Blueprint $table) {
            // Who uploaded it, so only they can delete or submit it. Older
            // rows stay null and expire with DeleteStaleTemporaryUploadsJob.
            $table->foreignUuid('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temporary_uploads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
