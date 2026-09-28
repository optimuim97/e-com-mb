<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Validation\ValidationException;

class PinService
{
    public function __construct(private readonly Hasher $hasher) {}

    public function set(User $user, string $pin, ?string $currentPin): void
    {
        if ($user->order_pin) {
            if (! $currentPin || ! $this->hasher->check($currentPin, $user->order_pin)) {
                throw ValidationException::withMessages([
                    'current_pin' => ['Code PIN actuel incorrect.'],
                ]);
            }
        }

        $user->forceFill(['order_pin' => $this->hasher->make($pin)])->save();
    }

    public function verify(User $user, string $pin): bool
    {
        if (! $user->order_pin) {
            throw ValidationException::withMessages([
                'pin' => ['Aucun code PIN défini.'],
            ]);
        }

        return $this->hasher->check($pin, $user->order_pin);
    }
}
