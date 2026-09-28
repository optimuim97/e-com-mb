<?php

namespace Modules\Auth\Services\Social;

use Modules\Auth\Services\Social\Contracts\SocialProvider;

/**
 * Résout un fournisseur social par sa clé.
 * Ajouter un fournisseur = l'enregistrer dans $providers.
 */
class SocialAuthManager
{
    /** @var array<string,class-string<SocialProvider>> */
    private array $providers = [
        'facebook' => FacebookProvider::class,
        'google'   => GoogleProvider::class,
    ];

    public function supports(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function driver(string $key): SocialProvider
    {
        if (!$this->supports($key)) {
            throw new \InvalidArgumentException("Fournisseur social inconnu : {$key}");
        }

        return app($this->providers[$key]);
    }
}
