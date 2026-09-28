<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // bronze | silver | gold
            $table->string('name');                    // Club Bronze
            $table->string('icon')->default('🥉');
            $table->string('color')->nullable();
            $table->unsignedInteger('points_min')->default(0);
            $table->unsignedInteger('points_max')->nullable();
            $table->unsignedTinyInteger('discount_pct')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed par défaut
        DB::table('program_tiers')->insert([
            ['slug' => 'bronze', 'name' => 'Club Bronze', 'icon' => '🥉', 'points_min' => 0,    'points_max' => 499,  'discount_pct' => 5,  'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'silver', 'name' => 'Club Argent', 'icon' => '🥈', 'points_min' => 500,  'points_max' => 1999, 'discount_pct' => 10, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'gold',   'name' => 'Club Or',     'icon' => '🥇', 'points_min' => 2000, 'points_max' => null, 'discount_pct' => 15, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('program_tiers');
    }
};
