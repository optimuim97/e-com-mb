<?php

namespace Modules\Order\Services\OrderCreation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PinManager
{
    public function ensureUserHasPin(User $user): ?string
    {
        if ($user->order_pin) {
            return null;
        }

        $generatedPin = $this->generatePin();
        $user->forceFill(['order_pin' => Hash::make($generatedPin)])->save();

        return $generatedPin;
    }

    private function generatePin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
