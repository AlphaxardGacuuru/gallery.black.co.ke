<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "One photo per user per competition" is now enforced in application
     * code (PhotoController::store(), counted against 1 + paid extra
     * slots) rather than the database, since a hard two-column unique
     * index can't express "up to N, where N varies per user."
     */
    public function up(): void
    {
        if (! $this->indexExists()) {
            return;
        }

        Schema::table('photos', function (Blueprint $table) {
            $table->dropUnique(['competition_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists()) {
            return;
        }

        Schema::table('photos', function (Blueprint $table) {
            $table->unique(['competition_id', 'user_id']);
        });
    }

    private function indexExists(): bool
    {
        return in_array(
            'photos_competition_id_user_id_unique',
            array_column(Schema::getIndexes('photos'), 'name'),
            true
        );
    }
};
