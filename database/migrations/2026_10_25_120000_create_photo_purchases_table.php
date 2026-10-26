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
        Schema::create('photo_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('photo_id')
                ->constrained('photos')
                ->cascadeOnDelete();
            $table->foreignUuid('buyer_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUuid('seller_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('seller_amount');
            $table->string('status')->default('pending');
            $table->string('kopokopo_reference')->nullable();
            $table->foreignUuid('mpesa_transaction_id')
                ->nullable()
                ->constrained('mpesa_transactions')
                ->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_reference')->nullable();
            $table->timestamp('payout_paid_at')->nullable();
            $table->timestamps();

            $table->index(['photo_id', 'buyer_id', 'status']);
            $table->index('kopokopo_reference');
            $table->index('payout_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_purchases');
    }
};
