<?php

namespace Modules\Auth\Services\Social;

use Illuminate\Support\Facades\Http;
use Modules\Auth\Services\Social\Contracts\SocialProvider;

/**
 * Fournisseur Facebook (OAuth2) — implémentation native sans dépendance.
 * Réutilisable tel quel : seules les clés config/services.facebook changent.
 */
class FacebookProvider implements SocialProvider
{
    private string $clientId;
    private string $clientSecret;
    private string $redirect;
    private string $version;

    public function __construct()
    {
        $this->clientId     = (string) config('services.facebook.client_id');
        $this->clientSecret = (string) config('services.facebook.client_secret');
        $this->redirect     = (string) config('services.facebook.redirect');
        $this->version      = (string) config('services.facebook.graph_version', 'v19.0');
    }

    public function key(): string
    {
        return 'facebook';
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
            'scope'         => 'email,public_profile',
        ]);

        return "https://www.facebook.com/{$this->version}/dialog/oauth?{$params}";
    }

    public function userFromCode(string $code): array
    {
        // 1) Échange du code contre un access_token
        $tokenRes = Http::get("https://graph.facebook.com/{$this->version}/oauth/access_token", [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirect,
            'code'          => $code,
        ]);

        if (!$tokenRes->successful() || empty($tokenRes['access_token'])) {
            throw new \RuntimeException('Échange du code Facebook échoué : ' . $tokenRes->body());
        }

        $accessToken = $tokenRes['access_token'];

        // 2) Récupération du profil
        $userRes = Http::get("https://graph.facebook.com/{$this->version}/me", [
            'fields'       => 'id,name,email,picture.type(large)',
            'access_token' => $accessToken,
        ]);

        if (!$userRes->successful() || empty($userRes['id'])) {
            throw new \RuntimeException('Récupération du profil Facebook échouée : ' . $userRes->body());
        }

        $data = $userRes->json();

        return [
            'provider_id' => (string) $data['id'],
            'name'        => $data['name']  ?? null,
            'email'       => $data['email'] ?? null,
            'avatar'      => $data['picture']['data']['url'] ?? null,
        ];
    }
}
