<?php

namespace Modules\Auth\Services\Social;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\Shop;

/**
 * Trouve ou crée l'utilisateur correspondant à un profil social,
 * puis émet un token Sanctum. Indépendant du fournisseur.
 */
class SocialAuthService
{
    private const TOKEN_NAME = 'auth_token';

    /**
     * @param array{provider_id:string,name:?string,email:?string,avatar:?string} $social
     * @return array{user:User,token:string,created:bool}
     */
    public function authenticate(string $provider, array $social): array
    {
        return DB::transaction(function () use ($provider, $social) {
            $providerColumn = $provider . '_id'; // ex: facebook_id
            $created = false;

            // 1) Déjà lié via l'id du fournisseur
            $user = User::where($providerColumn, $social['provider_id'])->first();

            // 2) Sinon, rattacher à un compte existant via l'email
            if (!$user && !empty($social['email'])) {
                $user = User::where('email', $social['email'])->first();
                if ($user) {
                    $user->forceFill([
                        $providerColumn => $social['provider_id'],
                        'auth_provider' => $provider,
                        'avatar_url'    => $user->avatar_url ?: $social['avatar'],
                    ])->save();
                }
            }

            // 3) Sinon, créer un nouveau compte
            if (!$user) {
                $user = User::create([
                    'name'           => $social['name'] ?: 'Client ' . Shop::name(),
                    'email'          => $social['email'] ?: $this->placeholderEmail($provider, $social['provider_id']),
                    'password'       => null,
                    $providerColumn  => $social['provider_id'],
                    'auth_provider'  => $provider,
                    'avatar_url'     => $social['avatar'],
                ]);

                // Rôle client par défaut si le système de rôles est présent
                if (method_exists($user, 'assignRole')) {
                    try { $user->assignRole('customer'); } catch (\Throwable) { /* rôle absent : ignore */ }
                }

                $created = true;
            }

            $token = $user->createToken(self::TOKEN_NAME)->plainTextToken;

            return ['user' => $user->load('roles'), 'token' => $token, 'created' => $created];
        });
    }

    private function placeholderEmail(string $provider, string $id): string
    {
        return "{$provider}-{$id}@social.boutique.local";
    }
}
