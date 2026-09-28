<?php

namespace App\Services\Notifications\Contracts;

interface NotificationChannelInterface
{
    /**
     * Envoie la notification pour un événement donné.
     *
     * @param string $event   Ex. "order.placed", "order.shipped"
     * @param array  $payload Données normalisées de l'événement
     */
    public function notify(string $event, array $payload): void;

    /**
     * Indique si ce canal doit réagir à cet événement.
     * Permet d'activer/désactiver un canal pour certains types d'événements.
     */
    public function shouldNotify(string $event): bool;
}
