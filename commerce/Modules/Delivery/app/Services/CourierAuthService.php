<?php

namespace Modules\Delivery\Services;

use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Validation\ValidationException;

class CourierAuthService
{
    private const TOKEN_NAME = 'courier_auth_token';

    public function __construct(private readonly Hasher $hasher)
    {
    }

    /**
     * Login livreur par email OU téléphone
     */
    public function login(string $identifier, string $password): array
    {
        // Le groupement des parenthèses n'est pas cosmétique. Sans lui, SQL
        // lisait « email = ? OR (phone = ? AND rôle courier) » : le contrôle
        // du rôle sautait sur la branche e-mail, et n'importe quel compte —
        // cliente, agent, gérante — obtenait avec son propre mot de passe un
        // jeton portant l'habilitation `courier`.
        $user = User::where(fn($q) => $q
                ->where('email', $identifier)
                ->orWhere('phone', $identifier))
            ->whereHas('roles', fn($q) => $q->where('name', 'courier'))
            ->first();

        if (!$user || !$this->hasher->check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['Identifiants livreur incorrects.'],
            ]);
        }

        if ($user->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'identifier' => ['Compte livreur désactivé. Contactez votre superviseur.'],
            ]);
        }

        // Supprimer les anciens tokens courier
        $user->tokens()->where('name', self::TOKEN_NAME)->delete();

        // Créer nouveau token avec abilities courier
        $token = $user->createToken(self::TOKEN_NAME, ['courier'])->plainTextToken;

        // Mise à jour last login
        $user->update(['last_login_at' => now()]);

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /**
     * Rafraîchir le token courier
     */
    public function refreshToken(User $user): array
    {
        // Supprimer le token actuel
        $user->currentAccessToken()?->delete();

        // Créer nouveau token
        $token = $user->createToken(self::TOKEN_NAME, ['courier'])->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /**
     * Déconnexion
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Enregistrer le token FCM
     */
    public function registerFcmToken(User $user, string $fcmToken): void
    {
        $user->update(['fcm_token' => $fcmToken]);
    }
}
