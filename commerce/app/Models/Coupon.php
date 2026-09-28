<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'minimum_amount',
        'usage_limit', 'used_count', 'is_active', 'starts_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value'          => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'is_active'      => 'boolean',
            'starts_at'      => 'datetime',
            'expires_at'     => 'datetime',
        ];
    }

    public function isValid(float $cartTotal): bool
    {
        if (! $this->is_active) return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        if ($this->minimum_amount && $cartTotal < $this->minimum_amount) return false;
        return true;
    }

    public function computeDiscount(float $cartTotal): float
    {
        return match ($this->type) {
            'percentage'    => round($cartTotal * $this->value / 100, 2),
            'fixed'         => min((float) $this->value, $cartTotal),
            'free_shipping' => 0,
            default         => 0,
        };
    }
}
