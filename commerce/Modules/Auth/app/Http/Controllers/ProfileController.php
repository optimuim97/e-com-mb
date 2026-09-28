<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Modules\Customer\Http\Resources\UserResource;

class ProfileController extends Controller
{
    /**
     * GET /profile
     * Retourne le profil de l'utilisateur connecté.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()->load('roles')));
    }

    /**
     * PUT /profile/info
     * Mise à jour du nom et du téléphone.
     */
    public function updateInfo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'string', 'max:30'],
        ]);

        $request->user()->update($data);

        return response()->json([
            'message' => 'Profil mis à jour.',
            'user'    => new UserResource($request->user()->fresh()->load('roles')),
        ]);
    }

    /**
     * PUT /profile/setup-account
     * Pour les comptes commande rapide : définir email réel + mot de passe.
     * Uniquement possible si l'email actuel est généré (@clients.local).
     */
    public function setupAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! str_ends_with($user->email ?? '', '@clients.local')) {
            return response()->json([
                'message' => 'Votre compte dispose déjà d\'un email réel.',
            ], 422);
        }

        $data = $request->validate([
            'email'    => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'email.required'         => 'L\'adresse email est obligatoire.',
            'email.email'            => 'L\'adresse email n\'est pas valide.',
            'email.unique'           => 'Cette adresse email est déjà utilisée.',
            'password.required'      => 'Le mot de passe est obligatoire.',
            'password.confirmed'     => 'Les mots de passe ne correspondent pas.',
            'password.min'           => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.letters'       => 'Le mot de passe doit contenir au moins une lettre.',
            'password.numbers'       => 'Le mot de passe doit contenir au moins un chiffre.',
        ]);

        $user->update([
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Révoquer tous les anciens tokens Sanctum et en créer un nouveau
        $user->tokens()->delete();
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'message'      => 'Compte configuré avec succès.',
            'access_token' => $token,
            'user'         => new UserResource($user->fresh()->load('roles')),
        ]);
    }

    /**
     * PUT /profile/password
     * Changement de mot de passe pour les comptes normaux.
     * Requiert le mot de passe actuel.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        // Les comptes rapides doivent passer par setup-account
        if (str_ends_with($user->email ?? '', '@clients.local')) {
            return response()->json([
                'message' => 'Veuillez d\'abord configurer votre email via /profile/setup-account.',
            ], 422);
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'password.required'         => 'Le nouveau mot de passe est obligatoire.',
            'password.confirmed'        => 'Les mots de passe ne correspondent pas.',
            'password.min'              => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.letters'          => 'Le mot de passe doit contenir au moins une lettre.',
            'password.numbers'          => 'Le mot de passe doit contenir au moins un chiffre.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Mot de passe modifié avec succès.']);
    }
}
