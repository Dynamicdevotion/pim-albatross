<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ImportGestionali\Support\MappingTarget;
use Modules\ImportGestionali\Support\ProductRowImporter;
use Modules\ImportGestionali\Support\RowOutcome;
use Modules\ImportGestionali\Support\TaxonomyResolution;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Tests\TestCase;

class ProductRowImporterTaxonomiesTest extends TestCase
{
    use RefreshDatabase;

    private Taxonomy $colore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        Storage::fake('public');

        $this->colore = Taxonomy::create(['slug' => 'colore']);
        $this->colore->translations()->create(['locale' => 'it', 'name' => 'Colore']);
    }

    private function term(string $name, string $slug): TaxonomyTerm
    {
        $term = $this->colore->terms()->create(['slug' => $slug]);
        $term->translations()->create(['locale' => 'it', 'name' => $name]);

        return $term;
    }

    private function key(): string
    {
        return MappingTarget::forTaxonomy($this->colore->id);
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function import(array $extra, bool $createMissing = false, bool $replace = false, bool $updateExisting = false, bool $dryRun = false): RowOutcome
    {
        $seen = [];

        return ProductRowImporter::make($createMissing, $replace)->import(
            array_merge(['sku' => 'P1', 'name' => 'Prodotto'], $extra),
            2,
            $updateExisting,
            $seen,
            $dryRun,
        );
    }

    public function test_resolved_terms_are_linked_through_the_pivot(): void
    {
        $rosso = $this->term('Rosso', 'rosso');
        $blu = $this->term('Blu', 'blu');

        $outcome = $this->import([$this->key() => 'Rosso|Blu']);

        $this->assertSame('created', $outcome->action);
        $this->assertSame([], $outcome->warnings);
        $this->assertEqualsCanonicalizing(
            [$rosso->id, $blu->id],
            Product::where('sku', 'P1')->sole()->taxonomyTerms()->pluck('taxonomy_terms.id')->all(),
        );
    }

    public function test_an_unknown_term_is_a_warning_not_a_skip(): void
    {
        $this->term('Rosso', 'rosso');

        $outcome = $this->import([$this->key() => 'Rosso|Verde']);

        $this->assertSame('created', $outcome->action);
        $this->assertCount(1, $outcome->warnings);
        $this->assertStringContainsString('«Verde»', $outcome->warnings[0]);
        $this->assertStringContainsString('Colore', $outcome->warnings[0]);
        $this->assertCount(1, Product::where('sku', 'P1')->sole()->taxonomyTerms);
    }

    public function test_create_missing_terms_adds_the_term_and_links_it(): void
    {
        $outcome = $this->import([$this->key() => 'Verde'], createMissing: true);

        $this->assertSame('created', $outcome->action);
        $this->assertSame([], $outcome->warnings);
        $term = TaxonomyTerm::where('taxonomy_id', $this->colore->id)->sole();
        $this->assertSame('Verde', $term->name);
        $this->assertSame([$term->id], Product::where('sku', 'P1')->sole()->taxonomyTerms()->pluck('taxonomy_terms.id')->all());
    }

    public function test_add_only_by_default_keeps_terms_the_product_already_had(): void
    {
        $verde = $this->term('Verde', 'verde');
        $rosso = $this->term('Rosso', 'rosso');

        $product = Product::factory()->create(['sku' => 'P1']);
        $product->translations()->create(['locale' => 'it', 'name' => 'Esistente']);
        $product->taxonomyTerms()->attach($verde->id);

        $this->import([$this->key() => 'Rosso'], updateExisting: true);

        $this->assertEqualsCanonicalizing(
            [$verde->id, $rosso->id],
            $product->fresh()->taxonomyTerms()->pluck('taxonomy_terms.id')->all(),
        );
    }

    public function test_replace_toggle_swaps_the_terms_of_the_mapped_taxonomy(): void
    {
        $verde = $this->term('Verde', 'verde');
        $rosso = $this->term('Rosso', 'rosso');

        $product = Product::factory()->create(['sku' => 'P1']);
        $product->translations()->create(['locale' => 'it', 'name' => 'Esistente']);
        $product->taxonomyTerms()->attach($verde->id);

        $this->import([$this->key() => 'Rosso'], replace: true, updateExisting: true);

        $this->assertSame(
            [$rosso->id],
            $product->fresh()->taxonomyTerms()->pluck('taxonomy_terms.id')->all(),
        );
    }

    public function test_replace_leaves_the_taxonomy_untouched_when_nothing_resolves(): void
    {
        $verde = $this->term('Verde', 'verde');

        $product = Product::factory()->create(['sku' => 'P1']);
        $product->translations()->create(['locale' => 'it', 'name' => 'Esistente']);
        $product->taxonomyTerms()->attach($verde->id);

        $outcome = $this->import([$this->key() => 'Giallo'], replace: true, updateExisting: true);

        $this->assertCount(1, $outcome->warnings);
        $this->assertSame([$verde->id], $product->fresh()->taxonomyTerms()->pluck('taxonomy_terms.id')->all());
    }

    public function test_an_empty_taxonomy_cell_changes_nothing(): void
    {
        $verde = $this->term('Verde', 'verde');

        $product = Product::factory()->create(['sku' => 'P1']);
        $product->translations()->create(['locale' => 'it', 'name' => 'Esistente']);
        $product->taxonomyTerms()->attach($verde->id);

        $this->import([$this->key() => '   '], replace: true, updateExisting: true);

        $this->assertSame([$verde->id], $product->fresh()->taxonomyTerms()->pluck('taxonomy_terms.id')->all());
    }

    public function test_dry_run_reports_the_resolution_and_writes_nothing(): void
    {
        $this->term('Rosso', 'rosso');

        $outcome = $this->import([$this->key() => 'Rosso|Verde'], createMissing: true, dryRun: true);

        $this->assertSame('created', $outcome->action);
        $this->assertCount(1, $outcome->taxonomies);

        $resolution = $outcome->taxonomies[0];
        $this->assertSame('Colore', $resolution->taxonomyName);
        $this->assertSame(TaxonomyResolution::FOUND, $resolution->terms[0]['status']);
        $this->assertSame(TaxonomyResolution::WILL_CREATE, $resolution->terms[1]['status']);

        $this->assertSame(0, Product::count());
        $this->assertSame(1, TaxonomyTerm::count(), 'dry run must not create terms');
    }
}
