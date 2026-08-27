<?php

namespace Modules\Taxonomies\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * @extends Factory<TaxonomyTerm>
 */
class TaxonomyTermFactory extends Factory
{
    protected $model = TaxonomyTerm::class;

    public function definition(): array
    {
        return [
            'taxonomy_id' => Taxonomy::factory(),
            'parent_id' => null,
            'slug' => Str::slug(fake()->unique()->words(3, true)),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (TaxonomyTerm $term): void {
            if ($term->translations()->exists()) {
                return;
            }

            $term->translations()->create([
                'language_id' => Locales::idFor(Locales::baseCode()),
                'name' => Str::title(fake()->unique()->words(2, true)),
            ]);
        });
    }

    public function childOf(TaxonomyTerm $parent): static
    {
        return $this->state(fn (): array => [
            'taxonomy_id' => $parent->taxonomy_id,
            'parent_id' => $parent->getKey(),
        ]);
    }

    public function named(string $name, ?string $code = null): static
    {
        return $this
            ->state(['slug' => Str::slug($name)])
            ->afterCreating(fn (TaxonomyTerm $term) => $term->translations()->updateOrCreate(
                ['language_id' => Locales::idFor($code ?? Locales::baseCode())],
                ['name' => $name],
            ));
    }
}
