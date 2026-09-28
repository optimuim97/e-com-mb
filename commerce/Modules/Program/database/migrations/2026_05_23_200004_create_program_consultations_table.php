<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Type : skincare | product | routine | gift | other
            $table->string('type');
            // Canal : whatsapp | video | email | store
            $table->string('channel');
            $table->string('phone')->nullable();
            $table->timestamp('preferred_at')->nullable();
            $table->text('message')->nullable();
            // Statut : pending | confirmed | done | cancelled
            $table->string('status')->default('pending');
            $table->text('advisor_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_consultations');
    }
};
