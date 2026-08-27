<?php

namespace Modules\Taxonomies\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Models\Taxonomy;

/**
 * @extends Factory<Taxonomy>
 */
class TaxonomyFactory extends Factory
{
    protected $model = Taxonomy::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->unique()->words(3, true)),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Taxonomy $taxonomy): void {
            if ($taxonomy->translations()->exists()) {
                return;
            }

            $taxonomy->translations()->create([
                'language_id' => Locales::idFor(Locales::baseCode()),
                'name' => Str::title(fake()->unique()->words(2, true)),
            ]);
        });
    }

    /**
     * Give the taxonomy a specific name in a language (default: base).
     */
    public function named(string $name, ?string $code = null): static
    {
        return $this
            ->state(['slug' => Str::slug($name)])
            ->afterCreating(fn (Taxonomy $taxonomy) => $taxonomy->translations()->updateOrCreate(
                ['language_id' => Locales::idFor($code ?? Locales::baseCode())],
                ['name' => $name],
            ));
    }
}
