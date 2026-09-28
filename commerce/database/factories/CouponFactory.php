<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Coupon\Models\Coupon;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code'           => strtoupper(fake()->bothify('PROMO-####')),
            'type'           => 'percentage',
            'value'          => 10,
            'minimum_amount' => null,
            'usage_limit'    => null,
            'used_count'     => 0,
            'is_active'      => true,
            'starts_at'      => null,
            'expires_at'     => null,
        ];
    }

    public function percentage(float $pct): static
    {
        return $this->state(['type' => 'percentage', 'value' => $pct]);
    }

    public function fixed(float $amount): static
    {
        return $this->state(['type' => 'fixed', 'value' => $amount]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withMinimum(float $min): static
    {
        return $this->state(['minimum_amount' => $min]);
    }

    public function exhausted(): static
    {
        return $this->state(['usage_limit' => 1, 'used_count' => 1]);
    }
}
