<?php

namespace Modules\ExportProdotti\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ExportProdotti\Models\ExportRecord;
use Modules\ExportProdotti\Support\ExportRunner;
use Modules\ImportGestionali\Support\SpreadsheetReader;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ExportRunnerTest extends TestCase
{
    use RefreshDatabase;

    private PriceList $defaultList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->defaultList = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function product(string $sku, string $name, array $overrides = []): Product
    {
        $product = Product::factory()->create(array_merge(['sku' => $sku], $overrides));
        $product->translations()->create(['locale' => 'it', 'name' => $name]);

        return $product;
    }

    private function tmpPath(string $ext): string
    {
        return tempnam(sys_get_temp_dir(), 'pimexporttest').'.'.$ext;
    }

    /**
     * @return array{0: list<string>, 1: list<list<string>>} [header, rows]
     */
    private function readBack(string $path, string $ext): array
    {
        $shape = (new SpreadsheetReader)->inspect($path, $ext);

        return [$shape->header, $shape->sampleRows];
    }

    public function test_it_writes_only_the_products_matching_the_filters(): void
    {
        $active = $this->product('A', 'Alpha', ['status' => 'active']);
        $active->prices()->create(['price_list_id' => $this->defaultList->id, 'price' => 10]);
        $this->product('B', 'Beta', ['status' => 'draft']);

        $path = $this->tmpPath('csv');
        $count = app(ExportRunner::class)->write(
            ExportRunner::query(['status' => ['value' => 'active']]),
            ['sku', 'name', 'price', 'status'],
            'csv',
            $path,
        );

        $this->assertSame(1, $count);

        [$header, $rows] = $this->readBack($path, 'csv');
        $this->assertSame(['SKU', 'Nome (lingua base)', 'Prezzo (listino predefinito)', 'Stato'], $header);
        $this->assertCount(1, $rows);
        $this->assertSame(['A', 'Alpha', '10.00', 'active'], $rows[0]);
    }

    public function test_it_ignores_pagination_and_exports_every_match(): void
    {
        Product::factory()->count(30)->create()
            ->each(fn (Product $p) => $p->translations()->create(['locale' => 'it', 'name' => 'P'.$p->id]));

        $path = $this->tmpPath('csv');
        $count = app(ExportRunner::class)->write(ExportRunner::query([]), ['sku'], 'csv', $path);

        $this->assertSame(30, $count);
    }

    public function test_column_order_follows_the_enum_not_the_request(): void
    {
        $this->product('S1', 'Uno');

        $path = $this->tmpPath('csv');
        app(ExportRunner::class)->write(
            ExportRunner::query([]),
            ['status', 'sku', 'name'], // deliberately out of canonical order
            'csv',
            $path,
        );

        [$header] = $this->readBack($path, 'csv');
        $this->assertSame(['SKU', 'Nome (lingua base)', 'Stato'], $header);
    }

    public function test_variants_are_written_as_their_own_rows_after_the_container(): void
    {
        $parent = Product::factory()->variable()->create(['sku' => 'TS']);
        $parent->translations()->create(['locale' => 'it', 'name' => 'T-Shirt']);

        $red = Product::factory()->variantOf($parent)->create(['sku' => 'TS-RED', 'stock' => 5]);
        $red->translations()->create(['locale' => 'it', 'name' => 'T-Shirt Rossa']);
        $red->prices()->create(['price_list_id' => $this->defaultList->id, 'price' => 19.9]);

        // no own translation -> inherits the parent name
        Product::factory()->variantOf($parent)->create(['sku' => 'TS-BLUE', 'stock' => 2]);

        $path = $this->tmpPath('csv');
        $count = app(ExportRunner::class)->write(
            ExportRunner::query([]),
            ['sku', 'name', 'price', 'stock'],
            'csv',
            $path,
        );

        $this->assertSame(3, $count);

        [, $rows] = $this->readBack($path, 'csv');
        $this->assertSame(['TS', 'T-Shirt', '', ''], $rows[0]);
        $this->assertSame(['TS-RED', 'T-Shirt Rossa', '19.90', '5'], $rows[1]);
        $this->assertSame(['TS-BLUE', 'T-Shirt', '', '2'], $rows[2]);
    }

    public function test_it_produces_a_readable_xlsx(): void
    {
        $this->product('X1', 'Uno', ['status' => 'active']);

        $path = $this->tmpPath('xlsx');
        app(ExportRunner::class)->write(ExportRunner::query([]), ['sku', 'name', 'status'], 'xlsx', $path);

        [$header, $rows] = $this->readBack($path, 'xlsx');
        $this->assertSame(['SKU', 'Nome (lingua base)', 'Stato'], $header);
        $this->assertSame(['X1', 'Uno', 'active'], $rows[0]);
    }

    public function test_run_generates_the_file_and_completes_the_record(): void
    {
        $this->product('R1', 'Rec');

        $record = ExportRecord::factory()->create([
            'format' => 'csv',
            'columns' => ['sku', 'name'],
            'filters' => [],
            'status' => 'pending',
        ]);

        app(ExportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame('completed', $record->status);
        $this->assertSame(1, $record->row_count);
        $this->assertNotNull($record->stored_path);
        $this->assertTrue(Storage::disk('local')->exists($record->stored_path));
        $this->assertNotNull($record->finished_at);
    }
}
