<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
            ['key' => 'whatsapp_admin_number',   'value' => '',      'created_at' => now(), 'updated_at' => now()],
            ['key' => 'whatsapp_notify_customer', 'value' => 'false', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'shop_name',                'value' => config('app.name', 'Ma Boutique'), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
