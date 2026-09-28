<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages entrants du WhatsApp Business, conservés pour être traités.
 *
 * Le webhook se contentait de journaliser : suffisant pour vérifier que Meta
 * appelle bien l'URL, inutilisable pour travailler. Une commande arrivée
 * pendant la nuit se retrouvait dans `laravel.log`, mêlée au reste, et
 * disparaissait à la rotation. La boutique vend par WhatsApp — ce flux est
 * une file de travail, pas une trace technique.
 *
 * Cette table est le point de raccordement de la double saisie : chaque
 * message reçu est soit converti en commande, soit écarté explicitement.
 * Ce qui reste en `pending` est exactement ce qui n'a pas encore été traité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();

            // Identifiant Meta (wamid.…). Unique : Meta rejoue un événement
            // tant qu'il n'a pas reçu de 2xx, et un doublon créerait une
            // seconde commande pour le même client.
            $table->string('wa_message_id')->unique();

            $table->string('from_phone', 32)->index();
            $table->string('contact_name')->nullable();
            $table->string('type', 32)->default('text');
            $table->text('body')->nullable();

            // pending : à traiter · converted : commande créée · ignored : écarté
            $table->string('status', 20)->default('pending')->index();

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();

            // Motif d'écartement (« pas une commande », « doublon »…) : sans
            // lui, un message ignoré ne se distingue pas d'un oubli.
            $table->string('handling_note')->nullable();

            // Horodatage Meta, pas celui de la réception : un renvoi tardif
            // ne doit pas faire remonter un vieux message en tête de file.
            $table->timestamp('received_at')->index();
            $table->timestamp('handled_at')->nullable();

            // Charge utile brute — les formats Meta évoluent, et on ne
            // rattrapera pas ce qu'on n'a pas gardé.
            $table->json('payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
