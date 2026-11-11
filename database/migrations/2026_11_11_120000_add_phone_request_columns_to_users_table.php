<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // How many times an admin asked for their M-Pesa phone number,
            // and when they last did.
            $table->unsignedInteger('phone_requests_count')
                ->default(0)
                ->after('hashed_phone');
            $table->timestamp('phone_requested_at')
                ->nullable()
                ->after('phone_requests_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_requests_count', 'phone_requested_at']);
        });
    }
};
