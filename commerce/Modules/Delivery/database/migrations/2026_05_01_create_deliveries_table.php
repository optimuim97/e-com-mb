<?php
// Modules/Delivery/Database/Migrations/2026_05_01_create_deliveries_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique()->index();

            // Relations
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();

            // Status & Types
            $table->enum('status', [
                'PENDING',      // EN_ATTENTE
                'IN_PROGRESS',  // EN_COURS
                'DELIVERED',    // LIVRE
                'FAILED',       // ECHOUE
                'CANCELLED'     // ANNULE
            ])->default('PENDING')->index();

            $table->enum('delivery_type', [
                'LOCAL_STANDARD',
                'LOCAL_EXTENDED',
                'NATIONAL_EXPEDITION',
                'REGIONAL_AFRICA',
                'INTERNATIONAL_GP',
                'INTERNATIONAL_DHL'
            ]);

            $table->enum('payment_flow', ['COD', 'PREPAID', 'MIXED']);

            // Amounts (in cents/smallest unit)
            $table->integer('product_amount');     // Prix des produits
            $table->integer('delivery_fee');        // Frais de livraison
            $table->integer('total_amount');        // Total à collecter

            // QR Code
            $table->text('qr_code');
            $table->text('qr_code_hash')->nullable();
            $table->timestamp('qr_code_expires_at')->nullable();

            // Confirmation data
            $table->text('signature_base64')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('delivery_lat', 10, 8)->nullable();
            $table->decimal('delivery_lng', 11, 8)->nullable();

            // Metadata
            $table->timestamp('assigned_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->index(['courier_id', 'status']);
            $table->index(['courier_id', 'assigned_at']);
            $table->index(['status', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
