<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('tier')->default('bronze');  // bronze | silver | gold
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('points_lifetime')->default(0); // jamais décrémenté
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_memberships');
    }
};
