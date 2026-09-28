<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Vente flash
            $table->boolean('is_flash_sale')->default(false)->after('is_featured');
            $table->decimal('flash_price', 12, 2)->nullable()->after('is_flash_sale');
            $table->timestamp('flash_starts_at')->nullable()->after('flash_price');
            $table->timestamp('flash_ends_at')->nullable()->after('flash_starts_at');

            // Nouveau / Bestseller (auto-géré ou manuel)
            $table->boolean('is_new')->default(false)->after('flash_ends_at');
            $table->boolean('is_bestseller')->default(false)->after('is_new');

            $table->index(['is_flash_sale', 'flash_ends_at']);
            $table->index('is_new');
            $table->index('is_bestseller');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_flash_sale', 'flash_ends_at']);
            $table->dropIndex(['is_new']);
            $table->dropIndex(['is_bestseller']);
            $table->dropColumn([
                'is_flash_sale', 'flash_price',
                'flash_starts_at', 'flash_ends_at',
                'is_new', 'is_bestseller',
            ]);
        });
    }
};
