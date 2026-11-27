<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Everything about the verified badge: {"verified", "at",
            // "reason", "by"}, see User::markVerified(). Null means never
            // verified.
            $table->json('verification')->nullable()->after('email_verified_at');
            // Everything about a flag for suspected abuse: {"at", "reason",
            // "source", "refuted_at"}. Null means not flagged.
            $table->json('flag')->nullable()->after('verification');
            // Admin requests for the user's M-Pesa phone number: {"count",
            // "at"}. Null means never asked.
            $table->json('phone_request')->nullable()->after('hashed_phone');
        });

        DB::table('users')
            ->where('verified', true)
            ->update(['verification' => json_encode(['verified' => true])]);

        DB::table('users')
            ->whereNotNull('flagged_at')
            ->orderBy('id')
            ->each(fn (object $user) => DB::table('users')
                ->where('id', $user->id)
                ->update(['flag' => json_encode([
                    'at' => Carbon::parse($user->flagged_at)->toJSON(),
                    'reason' => $user->flag_reason,
                    'source' => $user->flag_source,
                    'refuted_at' => $user->flag_refuted_at ? Carbon::parse($user->flag_refuted_at)->toJSON() : null,
                ])]));

        DB::table('users')
            ->where('phone_requests_count', '>', 0)
            ->orderBy('id')
            ->each(fn (object $user) => DB::table('users')
                ->where('id', $user->id)
                ->update(['phone_request' => json_encode([
                    'count' => $user->phone_requests_count,
                    'at' => $user->phone_requested_at ? Carbon::parse($user->phone_requested_at)->toJSON() : null,
                ])]));

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'verified',
                'flagged_at',
                'flag_reason',
                'flag_source',
                'flag_refuted_at',
                'phone_requests_count',
                'phone_requested_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('verified')->default(false)->after('email_verified_at');
            $table->timestamp('flagged_at')->nullable()->after('verification');
            $table->string('flag_reason')->nullable()->after('flagged_at');
            $table->string('flag_source')->nullable()->after('flag_reason');
            $table->timestamp('flag_refuted_at')->nullable()->after('flag_source');
            $table->unsignedInteger('phone_requests_count')->default(0)->after('hashed_phone');
            $table->timestamp('phone_requested_at')->nullable()->after('phone_requests_count');
        });

        DB::table('users')
            ->where('verification->verified', true)
            ->update(['verified' => true]);

        DB::table('users')
            ->whereNotNull('flag')
            ->orWhereNotNull('phone_request')
            ->orderBy('id')
            ->each(function (object $user) {
                $flag = json_decode($user->flag ?? '[]', true);
                $phoneRequest = json_decode($user->phone_request ?? '[]', true);

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'flagged_at' => isset($flag['at']) ? Carbon::parse($flag['at']) : null,
                        'flag_reason' => $flag['reason'] ?? null,
                        'flag_source' => $flag['source'] ?? null,
                        'flag_refuted_at' => isset($flag['refuted_at']) ? Carbon::parse($flag['refuted_at']) : null,
                        'phone_requests_count' => $phoneRequest['count'] ?? 0,
                        'phone_requested_at' => isset($phoneRequest['at']) ? Carbon::parse($phoneRequest['at']) : null,
                    ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['verification', 'flag', 'phone_request']);
        });
    }
};
