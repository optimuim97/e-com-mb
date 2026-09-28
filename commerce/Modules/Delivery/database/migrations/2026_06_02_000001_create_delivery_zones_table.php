<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('group');               // ex: "Grand Abidjan", "Intérieur CI", "International"
            $table->string('name');                // ex: "Cocody Centre"
            $table->string('country', 2)->default('CI');
            $table->json('cities')->nullable();    // alias / synonymes ["cocody", "cocody centre"]
            $table->unsignedInteger('price');      // FCFA
            $table->string('price_unit')->default('flat'); // 'flat' ou 'per_kg'
            $table->unsignedInteger('free_threshold')->nullable(); // seuil franco optionnel
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['country', 'active']);
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
