<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('title');
            $table->text('body');
            $table->string('icon')->default('megaphone');
            $table->string('primary_label');
            $table->string('primary_url')->nullable();
            $table->string('secondary_label')->nullable();
            $table->string('audience')->default('all');
            $table->integer('priority')->default(0);
            $table->unsignedInteger('cooldown_hours')->default(24);
            $table->unsignedInteger('max_impressions')->nullable();
            $table->boolean('stop_after_click')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        // Replaces the old hard-coded referral modal, so the prompt keeps
        // showing after this ships. Copy and limits are editable in admin.
        DB::table('notices')->insert([
            'id' => (string) Str::uuid(),
            'key' => 'refer-a-friend',
            'title' => 'Earn KES {rewardAmount}',
            'body' => 'Refer {threshold} friends and get KES {rewardAmount} sent straight to your M-Pesa.',
            'icon' => 'gift',
            'primary_label' => 'Go to referrals',
            'primary_url' => '/settings/referrals',
            'secondary_label' => 'Close',
            'audience' => 'all',
            'priority' => 100,
            'cooldown_hours' => 12,
            'max_impressions' => null,
            'stop_after_click' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
