<?php

namespace Modules\Auth\Services;

use App\Models\User;
use App\Mail\Auth\CustomerOtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\AuthOtp;

class OtpService
{
    private const TTL_MINUTES        = 10;
    private const THROTTLE_MAX       = 3;
    private const THROTTLE_WINDOW    = 900; // 15 min
    private const MAX_VERIFY_ATTEMPTS = 5;

    public function __construct(
        private readonly CustomerWhatsAppService $whatsapp,
    ) {}

    /**
     * Génère un OTP et l'envoie via le canal demandé.
     * Réponse opaque : on ne révèle pas si l'identifiant existe (anti-énumération).
     */
    public function request(string $identifier, string $purpose, string $channel): void
    {
        $identifier = $this->normalize($identifier);
        Log::info("OTP request for identifier: $identifier, purpose: $purpose, channel: $channel");
        $this->assertNotThrottled($identifier);

        // Cherche un user dont email OU phone match
        $user = $this->findUser($identifier);
        Log::info("Found user for identifier: $identifier, user_id: " . ($user ? $user->id : 'null'));

        // Toujours incrémenter le throttle, même si l'user n'existe pas (anti-énumération)
        Cache::increment($this->throttleKey($identifier));
        Cache::put($this->throttleKey($identifier) . ':ttl', 1, self::THROTTLE_WINDOW);

        if (! $user) {
            return; // silencieux : pas de leak
        }

        // Génère un code à 6 chiffres
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Invalide tout OTP en cours pour cet identifier+purpose
        AuthOtp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        AuthOtp::create([
            'identifier' => $identifier,
            'purpose'    => $purpose,
            'code_hash'  => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $this->send($user, $code, $purpose, $channel);
    }

    /**
     * Vérifie l'OTP et marque consommé. Retourne le User si valide.
     */
    public function verify(string $identifier, string $purpose, string $code): User
    {
        $identifier = $this->normalize($identifier);

        $otp = AuthOtp::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp) {
            throw ValidationException::withMessages(['code' => ['Code invalide ou expiré.']]);
        }

        if ($otp->attempts >= self::MAX_VERIFY_ATTEMPTS) {
            $otp->update(['used_at' => now()]);
            throw ValidationException::withMessages(['code' => ['Trop de tentatives. Demandez un nouveau code.']]);
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            throw ValidationException::withMessages(['code' => ['Code invalide.']]);
        }

        $otp->update(['used_at' => now()]);

        $user = $this->findUser($identifier);
        if (! $user) {
            throw ValidationException::withMessages(['identifier' => ['Compte introuvable.']]);
        }

        return $user;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function normalize(string $identifier): string
    {
        $identifier = trim($identifier);
        if (str_contains($identifier, '@')) {
            return strtolower($identifier);
        }
        // téléphone : on retire tout sauf chiffres
        return preg_replace('/\D+/', '', $identifier);
    }

    private function findUser(string $identifier): ?User
    {
        if (str_contains($identifier, '@')) {
            return User::where('email', $identifier)->first();
        }

        // SQLite‑compatible: remove common phone formatting characters
        return User::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?",
            [$identifier]
        )->first();
    }

    private function throttleKey(string $identifier): string
    {
        return 'otp:throttle:' . sha1($identifier);
    }

    private function assertNotThrottled(string $identifier): void
    {
        $count = (int) Cache::get($this->throttleKey($identifier), 0);
        if ($count >= self::THROTTLE_MAX) {
            throw ValidationException::withMessages([
                'identifier' => ['Trop de demandes. Réessayez dans quelques minutes.'],
            ]);
        }
    }

    private function send(User $user, string $code, string $purpose, string $channel): void
    {
        $label = $purpose === AuthOtp::PURPOSE_PIN ? 'code PIN' : 'mot de passe';

        if ($channel === 'whatsapp') {
            $phone = $user->phone;
            if (! $phone) {
                throw ValidationException::withMessages([
                    'channel' => ['Pas de numéro WhatsApp lié à ce compte.'],
                ]);
            }
            $this->whatsapp->sendOtp($phone, $code, $label);
            return;
        }

        // Défaut : email
        if (! $user->email) {
            throw ValidationException::withMessages([
                'channel' => ['Pas d\'adresse email liée à ce compte.'],
            ]);
        }
        Mail::to($user->email)->send(new CustomerOtpMail($code, $label, self::TTL_MINUTES));
    }
}
