<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Achats : fournisseurs et bons d'achat.
 *
 * Jusqu'ici, une entrée en stock se saisissait à la main sans dire d'où elle
 * venait ni ce qu'elle avait coûté. La marge se calculait donc sur un
 * `cost_price` saisi une fois pour toutes, qui ne bougeait plus quand le
 * fournisseur augmentait ses prix.
 *
 * Un bon d'achat porte ses lignes et ses frais annexes (transport, douane).
 * À la réception, les articles entrent en stock par le registre et le coût
 * moyen pondéré du produit est recalculé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();

            // Fournisseur conservé même s'il est archivé : un bon d'achat sans
            // origine ne prouve plus rien sur le coût qu'il a fixé.
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            // draft = en préparation · received = entré en stock · cancelled
            $table->string('status', 16)->default('draft');
            $table->string('currency', 3)->default('XOF');

            // Somme des lignes, puis frais annexes répartis dessus.
            $table->decimal('items_total',   14, 2)->default(0);
            $table->decimal('shipping_cost', 14, 2)->default(0);
            $table->decimal('other_costs',   14, 2)->default(0);
            $table->decimal('total',         14, 2)->default(0);

            $table->date('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()
                  ->constrained('product_variants')->nullOnDelete();

            $table->integer('quantity');
            $table->decimal('unit_cost',  12, 2);
            $table->decimal('total_cost', 14, 2);

            // Prix de revient unitaire : coût d'achat + quote-part des frais
            // annexes. Figé à la réception, car les frais du bon peuvent encore
            // changer tant qu'il est en préparation.
            $table->decimal('landed_unit_cost', 12, 2)->nullable();

            $table->timestamps();

            $table->index('product_id');
        });

        // Coût moyen unitaire pondéré. Distinct de `cost_price`, qui reste le
        // tarif de référence saisi : le CMUP, lui, suit ce qu'on a réellement
        // payé, entrée après entrée.
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('average_cost', 12, 2)->nullable()->after('cost_price');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('average_cost', 12, 2)->nullable()->after('compare_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', fn (Blueprint $t) => $t->dropColumn('average_cost'));
        Schema::table('products',         fn (Blueprint $t) => $t->dropColumn('average_cost'));

        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
