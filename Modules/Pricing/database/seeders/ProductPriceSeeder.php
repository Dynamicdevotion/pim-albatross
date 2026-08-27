<?php

namespace Modules\Pricing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Models\ProductPrice;
use Modules\Products\Models\Product;

/**
 * Gives every product a price in every active price list, so the catalogue and
 * the bulk price editor have something to work with.
 *
 * Self-contained: if there are no products it creates a handful. Idempotent: an
 * existing (product_id, price_list_id) row is left untouched.
 */
class ProductPriceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PricingSeeder::class]);

        $lists = PriceList::query()->where('active', true)->orderByDesc('is_default')->get();

        if ($lists->isEmpty()) {
            return;
        }

        $products = Product::query()->with('prices')->get();

        if ($products->isEmpty()) {
            $products = Product::factory()->count(5)->create()->load('prices');
        }

        foreach ($products as $product) {
            $base = fake()->randomFloat(2, 5, 200);

            foreach ($lists as $index => $list) {
                if ($product->prices->contains('price_list_id', $list->id)) {
                    continue;
                }

                // non-default lists get a small deterministic discount
                $price = $index === 0 ? $base : round($base * (1 - 0.05 * $index), 2);

                ProductPrice::create([
                    'product_id' => $product->id,
                    'price_list_id' => $list->id,
                    'price' => $price,
                ]);
            }
        }
    }
}
