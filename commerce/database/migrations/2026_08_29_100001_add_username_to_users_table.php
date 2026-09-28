<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identifiant de connexion court, pour les comptes sans adresse e-mail.
 *
 * Un livreur n'a pas toujours de boîte aux lettres, et dicter
 * « livreur1@boutique.local » à quelqu'un qui tape sur un clavier de
 * téléphone est une mauvaise façon de commencer une tournée. « souattara » se
 * retient et se saisit.
 *
 * Nullable : les clientes et les comptes existants continuent de se connecter
 * par e-mail, et rien ne les oblige à en prendre un.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
