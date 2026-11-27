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
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('phone');
            $table->unsignedInteger('amount');
            $table->string('status')->default('pending');
            $table->string('failure_reason')->nullable();
            $table->string('kopokopo_reference')->nullable();
            $table->foreignUuid('mpesa_transaction_id')
                ->nullable()
                ->constrained('mpesa_transactions')
                ->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('kopokopo_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phone_verifications');
    }
};
