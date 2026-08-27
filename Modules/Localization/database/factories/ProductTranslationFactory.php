<?php

namespace Modules\Localization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Localization\Models\ProductTranslation;
use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;

/**
 * @extends Factory<ProductTranslation>
 */
class ProductTranslationFactory extends Factory
{
    protected $model = ProductTranslation::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'language_id' => Locales::idFor(Locales::baseCode()),
            'name' => Str::title(fake()->words(3, true)),
            'description' => '<p>'.fake()->paragraph().'</p>',
        ];
    }

    /**
     * Set the language of the translation by its code.
     */
    public function forLocale(string $code): static
    {
        return $this->state(fn (): array => ['language_id' => Locales::idFor($code)]);
    }

    /**
     * Leave the (nullable) description empty.
     */
    public function withoutDescription(): static
    {
        return $this->state(fn (): array => ['description' => null]);
    }
}
