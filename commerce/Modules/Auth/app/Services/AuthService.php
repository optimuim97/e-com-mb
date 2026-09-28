<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const TOKEN_NAME = 'auth_token';

    public function __construct(private readonly Hasher $hasher) {}

    public function register(array $data): array
    {
        $user = User::create($data);
        $user->assignRole('customer');

        $token = $user->createToken(self::TOKEN_NAME)->plainTextToken;

        return ['user' => $user->load('roles'), 'token' => $token];
    }

    /**
     * Connexion par adresse électronique ou par identifiant court.
     *
     * `$email` porte l'un ou l'autre : le champ du formulaire n'a pas changé
     * de nom, seule la recherche s'est élargie. L'identifiant est comparé en
     * minuscules, parce qu'un clavier de téléphone met volontiers une
     * majuscule au premier caractère.
     */
    public function login(string $email, string $password): array
    {
        $saisie = trim($email);

        $user = User::where('email', $saisie)
            ->orWhere('username', mb_strtolower($saisie))
            ->first();

        if (! $user || ! $this->hasher->check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        $user->tokens()->where('name', self::TOKEN_NAME)->delete();

        $token = $user->createToken(self::TOKEN_NAME)->plainTextToken;

        return ['user' => $user->load('roles'), 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
