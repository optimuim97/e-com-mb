<?php

namespace Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Delivery\Services\CourierAuthService;
use Modules\Delivery\Http\Requests\LoginRequest;
use Modules\Delivery\Http\Resources\CourierResource;

class AuthController extends Controller
{
    public function __construct(
        private readonly CourierAuthService $authService
    ) {}

    /**
     * Login livreur (email ou téléphone)
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $this->authService->login(
            $request->identifier,
            $request->password
        );

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'courier' => new CourierResource($user),
        ]);
    }

    /**
     * Rafraîchir le token
     *
     * @authenticated
     */
    public function refresh(Request $request): JsonResponse
    {
        ['user' => $user, 'token' => $token] = $this->authService->refreshToken(
            $request->user()
        );

        return response()->json([
            'token' => $token,
            'courier' => new CourierResource($user),
        ]);
    }

    /**
     * Déconnexion
     *
     * @authenticated
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Déconnecté avec succès.'
        ]);
    }

    /**
     * Enregistrer le token FCM pour les notifications push
     *
     * @authenticated
     */
    public function registerFcmToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $this->authService->registerFcmToken(
            $request->user(),
            $request->token
        );

        return response()->json([
            'message' => 'Token FCM enregistré.'
        ]);
    }

    /**
     * Profil du livreur connecté
     *
     * @authenticated
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(
            new CourierResource($request->user()->load('roles'))
        );
    }
}
