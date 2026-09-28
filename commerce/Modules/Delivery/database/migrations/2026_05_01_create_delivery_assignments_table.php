<?php
// Modules/Delivery/Database/Migrations/2026_05_01_create_delivery_assignments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('couriers');
            $table->foreignId('dispatcher_id')->constrained('users'); // From main app
            $table->enum('status', ['ASSIGNED', 'ACCEPTED', 'REJECTED', 'COMPLETED'])->default('ASSIGNED');
            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['delivery_id', 'courier_id', 'status'],
                'unique_active_assignment'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_assignments');
    }
};
