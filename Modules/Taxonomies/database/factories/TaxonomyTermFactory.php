<?php

namespace Modules\Taxonomies\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
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
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'taxonomy_id' => Taxonomy::factory(),
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }

    /**
     * Nest the term under an existing term (same taxonomy).
     */
    public function childOf(TaxonomyTerm $parent): static
    {
        return $this->state(fn (): array => [
            'taxonomy_id' => $parent->taxonomy_id,
            'parent_id' => $parent->getKey(),
        ]);
    }
}
