<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_lines', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('description');
            $table->json('items')->nullable()->after('price'); // slugs des produits constituant la gamme
        });
    }

    public function down(): void
    {
        Schema::table('product_lines', function (Blueprint $table) {
            $table->dropColumn(['price', 'items']);
        });
    }
};
