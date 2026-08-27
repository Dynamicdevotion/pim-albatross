<?php

namespace Modules\Pricing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Models\ProductPrice;
use Modules\Products\Models\Product;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    protected $model = ProductPrice::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'price_list_id' => PriceList::factory(),
            'price' => fake()->randomFloat(2, 1, 500),
        ];
    }

    public function forList(PriceList $list): static
    {
        return $this->state(fn (): array => ['price_list_id' => $list->getKey()]);
    }
}
