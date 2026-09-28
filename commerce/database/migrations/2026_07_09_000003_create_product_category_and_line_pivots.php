<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nouvelles règles métier :
 *  - un produit peut appartenir à PLUSIEURS catégories ;
 *  - un produit peut appartenir à PLUSIEURS gammes de soins.
 *
 * Les colonnes `products.category_id` et `products.product_line_id` sont
 * conservées (compat : catégorie/gamme « principale »), mais la vérité
 * relationnelle passe par ces deux pivots, remplis depuis l'existant.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_id', 'category_id']);
        });

        Schema::create('product_line_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_line_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_id', 'product_line_id']);
        });

        // Backfill depuis les colonnes existantes
        DB::statement('
            INSERT INTO category_product (product_id, category_id)
            SELECT id, category_id FROM products
            WHERE category_id IS NOT NULL AND deleted_at IS NULL
        ');
        DB::statement('
            INSERT INTO product_line_product (product_id, product_line_id)
            SELECT id, product_line_id FROM products
            WHERE product_line_id IS NOT NULL AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_line_product');
        Schema::dropIfExists('category_product');
    }
};
