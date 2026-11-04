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
        Schema::table('photo_images', function (Blueprint $table) {
            // Seed posts carry a second Unsplash photo here, tracked so it's
            // never posted again (same as photos.unsplash_id).
            $table->string('unsplash_id')->nullable()->unique()->after('thumbnail_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_images', function (Blueprint $table) {
            $table->dropUnique(['unsplash_id']);
            $table->dropColumn('unsplash_id');
        });
    }
};
