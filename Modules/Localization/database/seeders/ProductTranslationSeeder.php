<?php

namespace Modules\Localization\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Localization\Enums\Locale;
use Modules\Localization\Models\ProductTranslation;
use Modules\Products\Models\Product;

/**
 * Gives every product a translation in the base locale and in English.
 *
 * Self-contained: if there are no products yet it creates a handful with the
 * Product factory. Idempotent: an existing (product_id, locale) row is left
 * untouched, so the seeder is safe to run repeatedly.
 */
class ProductTranslationSeeder extends Seeder
{
    /**
     * Locales this seeder fills in for each product.
     *
     * @var list<Locale>
     */
    protected array $locales = [Locale::Italian, Locale::English];

    public function run(): void
    {
        $products = Product::query()->with('translations')->get();

        if ($products->isEmpty()) {
            $products = Product::factory()->count(5)->create()->load('translations');
        }

        foreach ($products as $product) {
            foreach ($this->locales as $locale) {
                if ($product->translations->contains('locale', $locale->value)) {
                    continue;
                }

                ProductTranslation::factory()
                    ->forLocale($locale)
                    ->create(['product_id' => $product->id]);
            }
        }
    }
}
