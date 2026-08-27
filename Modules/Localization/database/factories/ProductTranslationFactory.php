<?php

namespace Modules\Localization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Localization\Enums\Locale;
use Modules\Localization\Models\ProductTranslation;
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
            'locale' => Locale::default()->value,
            'name' => Str::title(fake()->words(3, true)),
            'description' => '<p>'.fake()->paragraph().'</p>',
        ];
    }

    /**
     * Set the locale of the translation.
     */
    public function forLocale(Locale|string $locale): static
    {
        return $this->state(fn (): array => [
            'locale' => $locale instanceof Locale ? $locale->value : $locale,
        ]);
    }

    /**
     * Leave the (nullable) description empty.
     */
    public function withoutDescription(): static
    {
        return $this->state(fn (): array => ['description' => null]);
    }
}
