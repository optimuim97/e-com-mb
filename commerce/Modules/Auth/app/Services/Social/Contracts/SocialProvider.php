<?php

namespace Modules\Auth\Services\Social\Contracts;

/**
 * Contrat d'un fournisseur d'authentification sociale (Facebook, Google, …).
 *
 * Conçu pour être réutilisable : pour ajouter un fournisseur, implémenter
 * cette interface et l'enregistrer dans SocialAuthManager.
 */
interface SocialProvider
{
    /** Clé du fournisseur (ex: "facebook"). */
    public function key(): string;

    /** Le fournisseur est-il correctement configuré (clés présentes) ? */
    public function isConfigured(): bool;

    /**
     * URL d'autorisation vers laquelle rediriger l'utilisateur.
     * $state = jeton anti-CSRF à renvoyer tel quel par le fournisseur.
     */
    public function authorizationUrl(string $state): string;

    /**
     * Échange le code d'autorisation contre les infos utilisateur normalisées.
     *
     * @return array{provider_id:string,name:?string,email:?string,avatar:?string}
     */
    public function userFromCode(string $code): array;
}
