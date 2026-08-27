<?php

namespace Modules\Taxonomies\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * A small set of realistic taxonomies to work with in the panel.
 *
 * Idempotent: taxonomies and terms are matched by slug and their base-language
 * name is upserted, so re-running only fills in what is missing.
 */
class TaxonomySeeder extends Seeder
{
    /**
     * Nested value = a parent term with children; string value = a leaf term.
     *
     * @var array<string, array<int|string, string|array<int, string>>>
     */
    protected array $taxonomies = [
        'Categoria' => [
            'Abbigliamento' => ['Magliette', 'Pantaloni', 'Giacche'],
            'Calzature' => ['Sneaker', 'Stivali'],
            'Accessori' => ['Cinture', 'Cappelli', 'Sciarpe'],
        ],
        'Colore' => ['Rosso', 'Blu', 'Verde', 'Nero', 'Bianco', 'Grigio'],
        'Taglia' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
        'Materiale' => ['Cotone', 'Lana', 'Pelle', 'Poliestere', 'Lino'],
    ];

    public function run(): void
    {
        $this->call(LanguageSeeder::class);

        $baseId = Locales::idFor('it');

        foreach ($this->taxonomies as $name => $terms) {
            $taxonomy = Taxonomy::firstOrCreate(['slug' => Str::slug($name)]);

            $taxonomy->translations()->updateOrCreate(
                ['language_id' => $baseId],
                ['name' => $name],
            );

            $this->seedTerms($taxonomy, $baseId, $terms);
        }
    }

    /**
     * @param  array<int|string, string|array<int, string>>  $terms
     */
    protected function seedTerms(Taxonomy $taxonomy, int $baseId, array $terms, ?TaxonomyTerm $parent = null): void
    {
        foreach ($terms as $key => $value) {
            $name = is_array($value) ? $key : $value;

            $term = $taxonomy->terms()->firstOrCreate(
                ['slug' => Str::slug((string) $name)],
                ['parent_id' => $parent?->getKey()],
            );

            $term->translations()->updateOrCreate(
                ['language_id' => $baseId],
                ['name' => $name],
            );

            if (is_array($value)) {
                $this->seedTerms($taxonomy, $baseId, $value, $term);
            }
        }
    }
}
