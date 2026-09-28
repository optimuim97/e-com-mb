<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cities and communes served, editable from the admin.
 *
 * The list used to live in the front-end bundle (src/data/cities-ci.js):
 * adding a locality meant a deployment, so a customer in a town the shop
 * actually delivers to could not name it at checkout.
 *
 * Two levels, one table: a row with no parent is a city (a department), a row
 * with a parent is one of its communes (a sub-prefecture). Delivery zones stay
 * a separate matter — they price what is sold here, they do not list it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->constrained('localities')
                // Removing a city removes its communes: an orphan commune
                // would be offered with no city to attach it to.
                ->cascadeOnDelete();
            $table->string('name');
            // Normalised name (lower case, no accents): what addresses are
            // matched against, since customers type "Attecoube" or "Attécoubé".
            $table->string('slug')->index();
            // Administrative region, for cities only — it tells two similar
            // names apart in the admin list.
            $table->string('region')->nullable();
            $table->char('country', 2)->default('CI');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['country', 'active']);
            $table->index(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localities');
    }
};
