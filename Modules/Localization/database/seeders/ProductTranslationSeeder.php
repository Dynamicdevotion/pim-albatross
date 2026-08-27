<?php

namespace Modules\Localization\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Localization\Models\ProductTranslation;
use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;

/**
 * Gives every product a translation in the base language and in English.
 *
 * Self-contained: if there are no products yet it creates a handful with the
 * Product factory. Idempotent: an existing (product_id, language_id) row is
 * left untouched, so the seeder is safe to run repeatedly.
 */
class ProductTranslationSeeder extends Seeder
{
    /**
     * Language codes this seeder fills in for each product.
     *
     * @var list<string>
     */
    protected array $codes = ['it', 'en'];

    public function run(): void
    {
        $products = Product::query()->with('translations')->get();

        if ($products->isEmpty()) {
            $products = Product::factory()->count(5)->create()->load('translations');
        }

        foreach ($products as $product) {
            foreach ($this->codes as $code) {
                $languageId = Locales::idFor($code);

                if ($languageId === null) {
                    continue;
                }

                if ($product->translations->contains('language_id', $languageId)) {
                    continue;
                }

                ProductTranslation::factory()
                    ->forLocale($code)
                    ->create(['product_id' => $product->id]);
            }
        }
    }
}
