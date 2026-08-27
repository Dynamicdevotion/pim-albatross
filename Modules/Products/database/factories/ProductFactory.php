<?php

namespace Modules\Products\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Products\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'external_id' => null,
            'status' => 'draft',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => 'active']);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => 'archived']);
    }
}
