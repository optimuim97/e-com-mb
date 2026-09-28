<?php

namespace Modules\Auth\Services\Social;

use Illuminate\Support\Facades\Http;
use Modules\Auth\Services\Social\Contracts\SocialProvider;

/**
 * Fournisseur Google (OAuth2 / OpenID Connect) — implémentation native sans dépendance.
 * Réutilisable tel quel : seules les clés config/services.google changent.
 */
class GoogleProvider implements SocialProvider
{
    private string $clientId;
    private string $clientSecret;
    private string $redirect;

    public function __construct()
    {
        $this->clientId     = (string) config('services.google.client_id');
        $this->clientSecret = (string) config('services.google.client_secret');
        $this->redirect     = (string) config('services.google.redirect');
    }

    public function key(): string
    {
        return 'google';
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '' && $this->redirect !== '';
    }

    public function authorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirect,
            'state'         => $state,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            // Toujours renvoyer un compte sélectionnable (utile si plusieurs sessions Google)
            'prompt'        => 'select_account',
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }

    public function userFromCode(string $code): array
    {
        // 1) Échange du code contre un access_token (POST form-urlencoded)
        $tokenRes = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirect,
            'grant_type'    => 'authorization_code',
            'code'          => $code,
        ]);

        if (!$tokenRes->successful() || empty($tokenRes['access_token'])) {
            throw new \RuntimeException('Échange du code Google échoué : ' . $tokenRes->body());
        }

        $accessToken = $tokenRes['access_token'];

        // 2) Récupération du profil via l'endpoint OpenID userinfo
        $userRes = Http::withToken($accessToken)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (!$userRes->successful() || empty($userRes['sub'])) {
            throw new \RuntimeException('Récupération du profil Google échouée : ' . $userRes->body());
        }

        $data = $userRes->json();

        return [
            'provider_id' => (string) $data['sub'],
            'name'        => $data['name']    ?? null,
            'email'       => $data['email']   ?? null,
            'avatar'      => $data['picture'] ?? null,
        ];
    }
}
