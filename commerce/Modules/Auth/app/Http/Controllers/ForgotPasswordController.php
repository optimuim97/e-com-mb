<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\AuthOtp;
use Modules\Auth\Services\OtpService;

class ForgotPasswordController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * POST /auth/password/forgot
     * Body : { identifier, channel: 'email'|'whatsapp' }
     */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([


            'identifier' => 'required|string|max:120',
            'channel'    => 'required|in:email,whatsapp',
        ]);

        $this->otp->request($data['identifier'], AuthOtp::PURPOSE_PASSWORD, $data['channel']);

        return response()->json([
            'message' => 'Si ce compte existe, un code a été envoyé.',
        ]);
    }

    /**
     * POST /auth/password/reset
     * Body : { identifier, code, password, password_confirmation }
     */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => 'required|string|max:120',
            'code'       => 'required|string|digits:6',
            'password'   => 'required|string|min:8|confirmed',
        ]);

        $user = $this->otp->verify($data['identifier'], AuthOtp::PURPOSE_PASSWORD, $data['code']);
        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        // Invalide les sessions actives (oblige à se reconnecter avec le nouveau mot de passe)
        $user->tokens()->delete();

        return response()->json(['message' => 'Mot de passe réinitialisé.']);
    }
}
