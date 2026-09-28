<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\Order;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'user_id'         => User::factory(),
            'number'          => 'ORD-TEST-' . str_pad($counter, 5, '0', STR_PAD_LEFT),
            'status'          => 'pending',
            'subtotal'        => 10000,
            'shipping_cost'   => 0,
            'discount_amount' => 0,
            'total'           => 10000,
            'currency'        => 'XOF',
            'shipping_address' => [
                'first_name'    => 'Test',
                'last_name'     => 'User',
                'address_line1' => 'Cocody, Abidjan',
                'city'          => 'Abidjan',
                'country'       => 'CI',
            ],
        ];
    }

    public function delivered(): static
    {
        return $this->state(['status' => 'delivered', 'delivered_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
