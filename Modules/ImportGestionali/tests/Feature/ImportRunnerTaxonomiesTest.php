<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ImportGestionali\Models\ImportRecord;
use Modules\ImportGestionali\Support\ImportRunner;
use Modules\ImportGestionali\Support\MappingTarget;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Tests\TestCase;

class ImportRunnerTaxonomiesTest extends TestCase
{
    use RefreshDatabase;

    private Taxonomy $colore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        Storage::fake('local');

        $this->colore = Taxonomy::create(['slug' => 'colore']);
        $this->colore->translations()->create(['locale' => 'it', 'name' => 'Colore']);
        $this->colore->terms()->create(['slug' => 'rosso'])
            ->translations()->create(['locale' => 'it', 'name' => 'Rosso']);
    }

    private function record(string $csv, bool $createMissing = false): ImportRecord
    {
        Storage::disk('local')->put('imports/run.csv', $csv);

        return ImportRecord::factory()->create([
            'original_filename' => 'run.csv',
            'stored_path' => 'imports/run.csv',
            'status' => 'pending',
            'create_missing_terms' => $createMissing,
            'mapping' => [0 => 'sku', 1 => 'name', 2 => MappingTarget::forTaxonomy($this->colore->id)],
            'meta' => ['delimiter' => ';', 'encoding' => null],
            'total_rows' => 2,
        ]);
    }

    public function test_a_colore_column_links_terms_and_notes_the_misses(): void
    {
        $record = $this->record(
            "Codice;Nome;Colore\nA1;Sedia;Rosso\nA2;Tavolo;Rosso|Verde\n",
        );

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame('completed', $record->status);
        $this->assertSame(2, $record->created_count);

        $this->assertEqualsCanonicalizing(
            ['Rosso'],
            Product::where('sku', 'A1')->sole()->taxonomyTerms->pluck('name')->all(),
        );

        $reasons = implode("\n", array_column($record->issues, 'reason'));
        $this->assertStringContainsString('termine «Verde» non trovato nella tassonomia Colore', $reasons);
    }

    public function test_create_missing_terms_makes_the_term_during_the_run(): void
    {
        $record = $this->record("Codice;Nome;Colore\nA2;Tavolo;Verde\n", createMissing: true);
        $record->update(['total_rows' => 1]);

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame('completed', $record->status);
        $this->assertSame([], $record->issues);
        $this->assertSame(2, TaxonomyTerm::where('taxonomy_id', $this->colore->id)->count());
        $this->assertEqualsCanonicalizing(
            ['Verde'],
            Product::where('sku', 'A2')->sole()->taxonomyTerms->pluck('name')->all(),
        );
    }
}
