<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kopokopo_transfers', function (Blueprint $table) {
            $table->string('recipient_name')->nullable()->after('amount');
            $table->string('recipient_phone')->nullable()->after('recipient_name');
        });

        // Older rows never stored the webhook's destinations, but the
        // transfer_batches they did store repeat each recipient's nickname
        // and phone number on its disbursement.
        DB::table('kopokopo_transfers')
            ->whereNotNull('transfer_batches')
            ->orderBy('id')
            ->each(function (object $transfer) {
                $batches = json_decode($transfer->transfer_batches, true);
                $disbursement = $batches[0]['disbursements'][0] ?? [];

                if (! isset($disbursement['nickname']) && ! isset($disbursement['phone_number'])) {
                    return;
                }

                DB::table('kopokopo_transfers')
                    ->where('id', $transfer->id)
                    ->update([
                        'recipient_name' => $disbursement['nickname'] ?? null,
                        'recipient_phone' => $disbursement['phone_number'] ?? null,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kopokopo_transfers', function (Blueprint $table) {
            $table->dropColumn(['recipient_name', 'recipient_phone']);
        });
    }
};
