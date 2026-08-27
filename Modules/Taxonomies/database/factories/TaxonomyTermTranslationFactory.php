<?php

namespace Modules\Taxonomies\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Modules\Taxonomies\Models\TaxonomyTermTranslation;

/**
 * @extends Factory<TaxonomyTermTranslation>
 */
class TaxonomyTermTranslationFactory extends Factory
{
    protected $model = TaxonomyTermTranslation::class;

    public function definition(): array
    {
        return [
            'taxonomy_term_id' => TaxonomyTerm::factory(),
            'language_id' => Locales::idFor(Locales::baseCode()),
            'name' => Str::title(fake()->unique()->words(2, true)),
        ];
    }

    public function forLocale(string $code): static
    {
        return $this->state(fn (): array => ['language_id' => Locales::idFor($code)]);
    }
}
