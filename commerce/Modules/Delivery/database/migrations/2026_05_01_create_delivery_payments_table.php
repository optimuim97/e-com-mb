<?php
// Modules/Delivery/Database/Migrations/2026_05_01_create_delivery_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('couriers');

            // Payment method
            $table->enum('payment_method', ['CASH', 'MOBILE_MONEY']);
            $table->enum('mobile_operator', ['WAVE', 'ORANGE_MONEY', 'MTN_MONEY'])->nullable();
            $table->string('transaction_reference')->nullable();

            // Amounts (in cents)
            $table->integer('amount_expected');
            $table->integer('amount_received');
            $table->integer('change_amount');

            $table->timestamp('paid_at');
            $table->timestamps();

            // Index
            $table->index(['delivery_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_payments');
    }
};
