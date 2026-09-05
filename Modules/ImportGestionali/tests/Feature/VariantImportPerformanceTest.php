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
use Tests\TestCase;

/**
 * Not part of the normal suite — a manual probe for the wall time of the
 * two-pass variant import on a realistic large file. Run it with:
 *
 *   IMPORT_BENCH=1 php artisan test --filter=VariantImportPerformanceTest
 *
 * It prints "rows in Ns" and the peak memory to STDERR.
 */
class VariantImportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_pass_variant_import_on_a_realistic_large_file(): void
    {
        if (! env('IMPORT_BENCH')) {
            $this->markTestSkipped('set IMPORT_BENCH=1 to run the variant-import performance probe');
        }

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        Storage::fake('local');

        $colore = Taxonomy::create(['slug' => 'colore']);
        $colore->translations()->create(['locale' => 'it', 'name' => 'Colore']);
        foreach (['Oro giallo', 'Oro bianco', 'Argento', 'Rosa'] as $name) {
            $colore->terms()->create(['slug' => \Illuminate\Support\Str::slug($name)])
                ->translations()->create(['locale' => 'it', 'name' => $name]);
        }

        // ~8000 rows: 1200 containers, ~5 variants each, plus ~800 simples.
        $containers = 1200;
        $simples = 800;
        $colours = ['Oro giallo', 'Oro bianco', 'Argento', 'Rosa'];

        $csv = "Codice;Codice Padre;Nome;Prezzo;Giacenza;Colore\n";

        for ($s = 1; $s <= $simples; $s++) {
            $csv .= "S{$s};;Prodotto semplice {$s};".(10 + $s % 90).";".($s % 20).";\n";
        }

        $rowCount = $simples;

        for ($c = 1; $c <= $containers; $c++) {
            // Container row placed AFTER some of its variants to exercise the
            // order-independent path.
            $variantCount = 4 + ($c % 3); // 4..6
            for ($v = 1; $v <= $variantCount; $v++) {
                $colour = $colours[($c + $v) % 4];
                $csv .= "P{$c}-{$v};P{$c};Anello {$c} misura ".(14 + $v).";".(80 + $c % 120).";".($v * 2).";{$colour}\n";
                $rowCount++;
            }
            $csv .= "P{$c};;Anello modello {$c};;;\n";
            $rowCount++;
        }

        Storage::disk('local')->put('imports/bench.csv', $csv);

        $record = ImportRecord::factory()->create([
            'original_filename' => 'bench.csv',
            'stored_path' => 'imports/bench.csv',
            'status' => 'pending',
            'update_existing' => false,
            'create_missing_terms' => false,
            'mapping' => [
                0 => 'sku',
                1 => 'parent_sku',
                2 => 'name',
                3 => 'price',
                4 => 'stock',
                5 => MappingTarget::forTaxonomy($colore->id),
            ],
            'meta' => ['delimiter' => ';', 'encoding' => null],
            'total_rows' => $rowCount,
        ]);

        $start = microtime(true);
        app(ImportRunner::class)->run($record);
        $elapsed = microtime(true) - $start;

        $record->refresh();

        fwrite(STDERR, sprintf(
            "\n[perf] variant import: %d rows in %.1fs (%.0f rows/s) — created %d, skipped %d — peak mem %.0f MB\n",
            $rowCount,
            $elapsed,
            $rowCount / $elapsed,
            $record->created_count,
            $record->skipped_count,
            memory_get_peak_usage(true) / 1048576,
        ));

        $this->assertSame('completed', $record->status);
        $this->assertSame($containers + $simples + array_sum(array_map(
            fn (int $c): int => 4 + ($c % 3),
            range(1, $containers),
        )), Product::count());
    }

    public function test_flat_simple_only_baseline_for_comparison(): void
    {
        if (! env('IMPORT_BENCH')) {
            $this->markTestSkipped('set IMPORT_BENCH=1 to run the baseline probe');
        }

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        Storage::fake('local');

        $rows = 8000;
        $csv = "Codice;Nome;Prezzo;Giacenza\n";
        for ($i = 1; $i <= $rows; $i++) {
            $csv .= "S{$i};Prodotto {$i};".(10 + $i % 90).';'.($i % 20)."\n";
        }
        Storage::disk('local')->put('imports/flat.csv', $csv);

        $record = ImportRecord::factory()->create([
            'original_filename' => 'flat.csv',
            'stored_path' => 'imports/flat.csv',
            'status' => 'pending',
            'mapping' => [0 => 'sku', 1 => 'name', 2 => 'price', 3 => 'stock'],
            'meta' => ['delimiter' => ';', 'encoding' => null],
            'total_rows' => $rows,
        ]);

        $start = microtime(true);
        app(ImportRunner::class)->run($record);
        $elapsed = microtime(true) - $start;

        fwrite(STDERR, sprintf(
            "\n[perf] flat baseline: %d rows in %.1fs (%.0f rows/s) — peak mem %.0f MB\n",
            $rows,
            $elapsed,
            $rows / $elapsed,
            memory_get_peak_usage(true) / 1048576,
        ));

        $this->assertSame('completed', $record->fresh()->status);
    }
}
