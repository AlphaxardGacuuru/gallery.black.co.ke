<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            // A random id the browser keeps, so a guest's events can be
            // followed and then linked to their account once they sign in.
            $table->uuid('anonymous_id')->nullable()->index()->after('user_id');
            // The page the browser was on, for events sent from the app.
            $table->string('path')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            $table->dropIndex(['anonymous_id']);
            $table->dropColumn(['anonymous_id', 'path']);
        });
    }
};
