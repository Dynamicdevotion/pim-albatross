<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ImportGestionali\Models\ImportRecord;
use Modules\ImportGestionali\Support\ImportRunner;
use Modules\ImportGestionali\Support\MappingTarget;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Tests\TestCase;

/**
 * End-to-end of the variant-aware {@see ImportRunner}: a mixed, deliberately
 * out-of-order file.
 */
class ImportRunnerVariantsTest extends TestCase
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

    /**
     * @param  array<int, string>  $mapping
     */
    private function record(string $csv, array $mapping, bool $updateExisting = false): ImportRecord
    {
        Storage::disk('local')->put('imports/run.csv', $csv);

        return ImportRecord::factory()->create([
            'original_filename' => 'run.csv',
            'stored_path' => 'imports/run.csv',
            'status' => 'pending',
            'update_existing' => $updateExisting,
            'mapping' => $mapping,
            'meta' => ['delimiter' => ';', 'encoding' => null],
            'total_rows' => substr_count($csv, "\n") - 1,
        ]);
    }

    private function reasons(ImportRecord $record): string
    {
        return implode("\n", array_column($record->issues, 'reason'));
    }

    public function test_an_out_of_order_file_builds_the_containers_and_their_variants(): void
    {
        $csv = "Codice;Codice Padre;Nome;Prezzo;Giacenza\n"
            ."RING-18;RING;Anello 18;;2\n"     // variant before its parent row
            ."PLAIN;;Collana;89;5\n"           // a plain simple product
            ."RING;;Anello Nozze;;\n"          // the container definition
            ."RING-20;RING;;;3\n";            // variant with no name -> inherits

        $record = $this->record($csv, [0 => 'sku', 1 => 'parent_sku', 2 => 'name', 3 => 'price', 4 => 'stock']);

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame('completed', $record->status);
        $this->assertSame(4, $record->created_count);
        $this->assertSame(0, $record->skipped_count);

        $ring = Product::where('sku', 'RING')->sole();
        $this->assertSame('variable', $ring->type->value);
        $this->assertNull($ring->stock);
        $this->assertEqualsCanonicalizing(['RING-18', 'RING-20'], $ring->variants->pluck('sku')->all());
        $this->assertSame('Anello Nozze', Product::where('sku', 'RING-20')->sole()->translate(Locales::baseCode())->name);
        $this->assertSame('simple', Product::where('sku', 'PLAIN')->sole()->type->value);
    }

    public function test_a_variant_whose_parent_is_nowhere_to_be_found_is_reported(): void
    {
        $csv = "Codice;Codice Padre;Nome\n"
            ."X-1;NOPE;Variante X\n"
            ."OK;;Prodotto OK\n";

        $record = $this->record($csv, [0 => 'sku', 1 => 'parent_sku', 2 => 'name']);

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame(1, $record->created_count);
        $this->assertSame(1, $record->skipped_count);
        $this->assertStringContainsString('padre con SKU «NOPE» non trovato', $this->reasons($record));
    }

    public function test_update_off_blocks_a_conversion_and_cascades_to_its_variants(): void
    {
        Product::factory()->create(['sku' => 'RING', 'type' => 'simple']);

        $csv = "Codice;Codice Padre;Nome\n"
            ."RING;;Anello\n"
            ."RING-18;RING;Anello 18\n";

        $record = $this->record($csv, [0 => 'sku', 1 => 'parent_sku', 2 => 'name'], updateExisting: false);

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame(0, $record->created_count);
        $this->assertSame(2, $record->skipped_count, 'the container row and its one variant are both skipped');
        $this->assertSame('simple', Product::where('sku', 'RING')->sole()->fresh()->type->value);

        // Both the container row (line 2) and the variant row (line 3) carry
        // the reason: the existing simple was not converted.
        $lines = array_column($record->issues, 'line');
        $this->assertEqualsCanonicalizing([2, 3], $lines);
        foreach ($record->issues as $issue) {
            $this->assertStringContainsString('non è stato convertito', $issue['reason']);
        }
    }

    public function test_the_shipped_sample_file_imports_cleanly(): void
    {
        $path = base_path('Modules/ImportGestionali/resources/samples/prodotti_gioielleria_test.csv');
        Storage::disk('local')->put('imports/sample.csv', file_get_contents($path));

        $record = ImportRecord::factory()->create([
            'original_filename' => 'prodotti_gioielleria_test.csv',
            'stored_path' => 'imports/sample.csv',
            'status' => 'pending',
            'update_existing' => false,
            'mapping' => [
                0 => 'sku',
                1 => 'parent_sku',
                2 => 'name',
                3 => 'description',
                4 => 'price',
                5 => 'stock',
                6 => 'status',
                7 => null,
                8 => null,
            ],
            'meta' => ['delimiter' => ',', 'encoding' => null],
            'total_rows' => 9,
        ]);

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame('completed', $record->status);
        // 2 simple + 2 containers + 5 variants
        $this->assertSame(9, $record->created_count);
        $this->assertSame(0, $record->skipped_count);

        $solitario = Product::where('sku', 'AN-SOLITARIO')->sole();
        $this->assertSame('variable', $solitario->type->value);
        $this->assertCount(3, $solitario->variants);
        $this->assertSame('Anello Solitario', Product::where('sku', 'AN-SOLIT-050-BIANCO')->sole()->translate(Locales::baseCode())->name);
    }

    public function test_taxonomy_columns_populate_a_variant_the_same_way_as_a_simple_product(): void
    {
        $csv = "Codice;Codice Padre;Nome;Colore\n"
            ."BR;;Bracciale;\n"
            ."BR-R;BR;Bracciale Rosso;Rosso\n";

        $record = $this->record($csv, [
            0 => 'sku',
            1 => 'parent_sku',
            2 => 'name',
            3 => MappingTarget::forTaxonomy($this->colore->id),
        ]);

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame('completed', $record->status);
        $this->assertEqualsCanonicalizing(
            ['Rosso'],
            Product::where('sku', 'BR-R')->sole()->taxonomyTerms->pluck('name')->all(),
        );
    }
}
