<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Photos a user saved to look at again, listed under Saved on their
        // profile.
        Schema::create('photo_bookmarks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('photo_id')
                ->constrained('photos')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'photo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_bookmarks');
    }
};
