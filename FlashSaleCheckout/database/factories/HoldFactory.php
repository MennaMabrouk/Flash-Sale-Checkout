<?php

namespace Database\Factories;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class HoldFactory extends Factory
{
    protected $model = Hold::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => 1,
            'expires_at' => now()->addMinutes($this->faker->numberBetween(1, 5)),
        ];
    }
}
