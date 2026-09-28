<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Product;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name'              => ucfirst($name),
            'slug'              => str($name)->slug(),
            'description'       => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'price'             => fake()->randomElement([5000, 8000, 12000, 15000, 25000]),
            'compare_price'     => null,
            'cost_price'        => null,
            'sku'               => strtoupper(fake()->bothify('RB-####-??')),
            'stock'             => fake()->numberBetween(5, 50),
            'type'              => 'simple',
            'has_variants'      => false,
            'track_stock'       => true,
            'is_active'         => true,
            'is_featured'       => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }

    public function priced(int $price): static
    {
        return $this->state(['price' => $price]);
    }
}
