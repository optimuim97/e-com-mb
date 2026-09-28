<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\AuthOtp;
use Modules\Auth\Services\OtpService;

class ForgotPinController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * POST /auth/pin/forgot
     * Body : { identifier, channel: 'email'|'whatsapp' }
     */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => 'required|string|max:120',
            'channel'    => 'required|in:email,whatsapp',
        ]);

        $this->otp->request($data['identifier'], AuthOtp::PURPOSE_PIN, $data['channel']);

        return response()->json([
            'message' => 'Si ce compte existe, un code a été envoyé.',
        ]);
    }

    /**
     * POST /auth/pin/reset
     * Body : { identifier, code, pin (4 chiffres) }
     */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => 'required|string|max:120',
            'code'       => 'required|string|digits:6',
            'pin'        => 'required|string|digits:4',
        ]);

        $user = $this->otp->verify($data['identifier'], AuthOtp::PURPOSE_PIN, $data['code']);
        $user->forceFill(['order_pin' => Hash::make($data['pin'])])->save();

        return response()->json(['message' => 'Code PIN réinitialisé.']);
    }
}
