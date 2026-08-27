<?php

namespace Modules\Products\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;
use Modules\Products\Support\VariantGenerator;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Tests\TestCase;

class VariantGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
    }

    /**
     * @return array{parent: Product, colour: array<string, TaxonomyTerm>, size: array<string, TaxonomyTerm>}
     */
    private function catalogue(): array
    {
        $parent = Product::factory()->variable()->create(['sku' => 'TSHIRT']);
        $parent->translations()->create(['locale' => 'it', 'name' => 'Maglietta', 'description' => '<p>base</p>']);
        $parent->translations()->create(['locale' => 'en', 'name' => 'T-shirt']);

        $colour = Taxonomy::factory()->named('Colore')->create();
        $size = Taxonomy::factory()->named('Taglia')->create();

        return [
            'parent' => $parent,
            'colour' => [
                'rosso' => TaxonomyTerm::factory()->named('Rosso')->for($colour)->create(),
                'blu' => TaxonomyTerm::factory()->named('Blu')->for($colour)->create(),
            ],
            'size' => [
                'm' => TaxonomyTerm::factory()->named('M')->for($size)->create(),
                'l' => TaxonomyTerm::factory()->named('L')->for($size)->create(),
            ],
        ];
    }

    public function test_combinations_is_the_cartesian_product_ordered_by_taxonomy(): void
    {
        $combos = VariantGenerator::combinations([
            10 => [1, 2],
            5 => [7, 8, 9],
        ]);

        // taxonomy 5 comes first (lower id)
        $this->assertSame(
            [[7, 1], [7, 2], [8, 1], [8, 2], [9, 1], [9, 2]],
            $combos,
        );
    }

    public function test_combinations_drops_empty_groups(): void
    {
        $this->assertSame([[1], [2]], VariantGenerator::combinations([3 => [1, 2], 4 => []]));
        $this->assertSame([], VariantGenerator::combinations([3 => [], 4 => []]));
    }

    public function test_normalize_selection_keeps_only_enabled_taxonomies_with_clean_ids(): void
    {
        $normalized = VariantGenerator::normalizeSelection(
            ['10' => ['1', '2', '2', null, ''], '11' => ['3'], '12' => []],
            [10, 12],
        );

        $this->assertSame([10 => [1, 2]], $normalized);
    }

    public function test_normalize_selection_with_no_enabled_list_keeps_every_non_empty_group(): void
    {
        $this->assertSame(
            [5 => [7], 6 => [8, 9]],
            VariantGenerator::normalizeSelection([5 => [7], 6 => [8, 9], 7 => []]),
        );
    }

    public function test_generate_creates_one_variant_per_combination_with_proposed_skus(): void
    {
        ['parent' => $parent, 'colour' => $c, 'size' => $s] = $this->catalogue();

        $result = (new VariantGenerator())->generate($parent, [
            $c['rosso']->taxonomy_id => [$c['rosso']->id, $c['blu']->id],
            $s['m']->taxonomy_id => [$s['m']->id, $s['l']->id],
        ]);

        $this->assertSame(4, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertEqualsCanonicalizing(
            ['TSHIRT-ROSSO-M', 'TSHIRT-ROSSO-L', 'TSHIRT-BLU-M', 'TSHIRT-BLU-L'],
            $parent->variants()->pluck('sku')->all(),
        );

        $variant = $parent->variants()->where('sku', 'TSHIRT-ROSSO-M')->sole();
        $this->assertSame(ProductType::Variant, $variant->type);
        $this->assertSame(0, $variant->stock);
        $this->assertEqualsCanonicalizing(
            [$c['rosso']->id, $s['m']->id],
            $variant->taxonomyTerms()->pluck('taxonomy_terms.id')->all(),
        );
    }

    public function test_generate_copies_parent_translations_as_the_starting_point(): void
    {
        ['parent' => $parent, 'colour' => $c] = $this->catalogue();

        (new VariantGenerator())->generate($parent, [
            $c['rosso']->taxonomy_id => [$c['rosso']->id],
        ]);

        $variant = $parent->variants()->sole();
        $this->assertSame('Maglietta', $variant->translate('it')->name);
        $this->assertSame('<p>base</p>', $variant->translate('it')->description);
        $this->assertSame('T-shirt', $variant->translate('en')->name);
    }

    public function test_generate_applies_sku_and_name_overrides_per_combination(): void
    {
        ['parent' => $parent, 'colour' => $c] = $this->catalogue();

        (new VariantGenerator())->generate(
            $parent,
            [$c['rosso']->taxonomy_id => [$c['rosso']->id, $c['blu']->id]],
            [
                0 => ['sku' => 'CUSTOM-RED', 'name' => 'Maglietta Rossa'],
                1 => ['name' => 'Maglietta Blu'],
            ],
        );

        $red = $parent->variants()->where('sku', 'CUSTOM-RED')->sole();
        $this->assertSame('Maglietta Rossa', $red->translate('it')->name);
        $this->assertSame('T-shirt', $red->translate('en')->name); // non-base untouched

        $blue = $parent->variants()->where('sku', 'TSHIRT-BLU')->sole();
        $this->assertSame('Maglietta Blu', $blue->translate('it')->name);
    }

    public function test_generate_skips_combinations_whose_sku_already_exists(): void
    {
        ['parent' => $parent, 'colour' => $c, 'size' => $s] = $this->catalogue();
        $generator = new VariantGenerator();

        $selection = [
            $c['rosso']->taxonomy_id => [$c['rosso']->id],
            $s['m']->taxonomy_id => [$s['m']->id],
        ];

        $first = $generator->generate($parent, $selection);
        $this->assertSame(1, $first['created']);

        // add a colour and re-run: only the new combination is created
        $selection[$c['rosso']->taxonomy_id][] = $c['blu']->id;
        $second = $generator->generate($parent, $selection);

        $this->assertSame(1, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(2, $parent->variants()->count());
    }

    public function test_generated_variants_inherit_parent_status(): void
    {
        ['parent' => $parent, 'colour' => $c] = $this->catalogue();
        $parent->update(['status' => 'active']);

        (new VariantGenerator())->generate($parent, [$c['rosso']->taxonomy_id => [$c['rosso']->id]]);

        $this->assertSame('active', $parent->variants()->sole()->status);
    }
}
