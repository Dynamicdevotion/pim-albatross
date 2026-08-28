<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ImportGestionali\Models\ImportRecord;
use Modules\ImportGestionali\Support\ImportRunner;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ImportRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        Storage::fake('local');
    }

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
            'created_count' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'issues' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    public function test_a_clean_file_creates_every_product(): void
    {
        $record = $this->record(
            "Codice;Nome;Prezzo\nA1;Sedia;10\nA2;Tavolo;20\n",
            [0 => 'sku', 1 => 'name', 2 => 'price'],
        );

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame('completed', $record->status);
        $this->assertSame(2, $record->created_count);
        $this->assertSame(0, $record->skipped_count);
        $this->assertSame(2, Product::count());
        $this->assertNotNull($record->finished_at);
    }

    public function test_partial_import_counts_and_lists_every_skip_reason(): void
    {
        Product::factory()->create(['sku' => 'EXIST']);

        $csv = "Codice;Nome;Prezzo\n"
            ."NEW1;Sedia;10\n"      // created
            ."NEW1;Doppione;12\n"   // skipped: duplicate in file
            ."BAD;Tavolo;abc\n"     // skipped: price not numeric
            ."EXIST;Gia;5\n"        // skipped: sku exists, update off
            .";SenzaCodice;7\n";    // skipped: sku missing

        $record = $this->record($csv, [0 => 'sku', 1 => 'name', 2 => 'price']);

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame('completed', $record->status);
        $this->assertSame(1, $record->created_count);
        $this->assertSame(4, $record->skipped_count);
        $this->assertCount(4, $record->issues);

        $reasons = implode("\n", array_column($record->issues, 'reason'));
        $this->assertStringContainsString('duplicato nel file', $reasons);
        $this->assertStringContainsString('prezzo non numerico', $reasons);
        $this->assertStringContainsString('già presente', $reasons);
        $this->assertStringContainsString('SKU mancante', $reasons);
    }

    public function test_update_existing_updates_instead_of_skipping(): void
    {
        Product::factory()->create(['sku' => 'U1', 'stock' => 1]);

        $record = $this->record(
            "Codice;Nome;Giacenza\nU1;Aggiornato;42\n",
            [0 => 'sku', 1 => 'name', 2 => 'stock'],
            updateExisting: true,
        );

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame(1, $record->updated_count);
        $this->assertSame(0, $record->created_count);
        $this->assertSame(42, Product::where('sku', 'U1')->sole()->stock);
    }

    public function test_a_vanished_file_fails_the_run_cleanly(): void
    {
        $record = ImportRecord::factory()->create([
            'stored_path' => 'imports/missing.csv',
            'status' => 'pending',
            'mapping' => [0 => 'sku'],
        ]);

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame('failed', $record->status);
        $this->assertNotNull($record->error_message);
    }
}
