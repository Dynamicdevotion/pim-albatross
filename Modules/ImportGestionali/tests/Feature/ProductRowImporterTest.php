<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ImportGestionali\Support\ProductRowImporter;
use Modules\ImportGestionali\Support\RowOutcome;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ProductRowImporterTest extends TestCase
{
    use RefreshDatabase;

    private PriceList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->list = PriceList::create(['name' => 'Standard', 'is_default' => true]);
    }

    private function import(array $row, int $line = 2, bool $updateExisting = false, bool $dryRun = false): RowOutcome
    {
        $seen = [];

        return ProductRowImporter::make()->import($row, $line, $updateExisting, $seen, $dryRun);
    }

    public function test_creates_a_simple_product(): void
    {
        $outcome = $this->import(['sku' => 'A1', 'name' => 'Sedia', 'price' => '49,90', 'stock' => '12']);

        $this->assertSame('created', $outcome->action);

        $product = Product::where('sku', 'A1')->sole();
        $this->assertSame('simple', $product->type->value);
        $this->assertSame(12, $product->stock);
        $this->assertSame('Sedia', $product->translate(Locales::baseCode())->name);
        $this->assertEqualsWithDelta(
            49.90,
            (float) $product->prices()->where('price_list_id', $this->list->id)->value('price'),
            0.001,
        );
    }

    public function test_parses_thousands_and_decimal_separators(): void
    {
        $this->import(['sku' => 'A2', 'name' => 'X', 'price' => '1.234,56']);
        $this->import(['sku' => 'A3', 'name' => 'Y', 'price' => '1234.56'], 3);

        $this->assertEqualsWithDelta(1234.56, (float) Product::where('sku', 'A2')->sole()->prices()->value('price'), 0.001);
        $this->assertEqualsWithDelta(1234.56, (float) Product::where('sku', 'A3')->sole()->prices()->value('price'), 0.001);
    }

    public function test_skips_an_existing_sku_when_update_is_off(): void
    {
        Product::factory()->create(['sku' => 'B1']);

        $outcome = $this->import(['sku' => 'B1', 'name' => 'X'], 5);

        $this->assertSame('skipped', $outcome->action);
        $this->assertStringContainsString('già presente', $outcome->reason);
    }

    public function test_updates_an_existing_sku_when_update_is_on(): void
    {
        $product = Product::factory()->create(['sku' => 'C1', 'stock' => 3]);
        $product->translations()->create(['language_id' => Locales::idFor(Locales::baseCode()), 'name' => 'Vecchio']);

        $outcome = $this->import(['sku' => 'C1', 'name' => 'Nuovo', 'stock' => '9'], 5, updateExisting: true);

        $this->assertSame('updated', $outcome->action);

        $product->refresh();
        $this->assertSame(9, $product->stock);
        $this->assertSame('Nuovo', $product->translate(Locales::baseCode())->name);
    }

    public function test_empty_values_do_not_touch_the_existing_product_on_update(): void
    {
        $product = Product::factory()->create(['sku' => 'D1', 'stock' => 7, 'status' => 'active']);
        $product->translations()->create(['language_id' => Locales::idFor(Locales::baseCode()), 'name' => 'Tenuto']);

        $this->import(['sku' => 'D1', 'name' => '', 'stock' => '', 'status' => ''], 3, updateExisting: true);

        $product->refresh();
        $this->assertSame(7, $product->stock);
        $this->assertSame('active', $product->status);
        $this->assertSame('Tenuto', $product->translate(Locales::baseCode())->name);
    }

    public function test_accepts_italian_status_synonyms(): void
    {
        $this->import(['sku' => 'S1', 'name' => 'X', 'status' => 'attivo']);
        $this->import(['sku' => 'S2', 'name' => 'Y', 'status' => 'Archiviato'], 3);

        $this->assertSame('active', Product::where('sku', 'S1')->sole()->status);
        $this->assertSame('archived', Product::where('sku', 'S2')->sole()->status);
    }

    public function test_new_product_defaults_to_draft_when_status_is_absent(): void
    {
        $this->import(['sku' => 'S3', 'name' => 'X']);

        $this->assertSame('draft', Product::where('sku', 'S3')->sole()->status);
    }

    public function test_reports_each_row_level_problem(): void
    {
        $this->assertStringContainsString('SKU mancante', $this->import(['sku' => '', 'name' => 'X'])->reason);
        $this->assertStringContainsString('nome mancante', $this->import(['sku' => 'N1'])->reason);
        $this->assertStringContainsString('prezzo non numerico', $this->import(['sku' => 'N2', 'name' => 'X', 'price' => '12,ab'])->reason);
        $this->assertStringContainsString('numero intero', $this->import(['sku' => 'N3', 'name' => 'X', 'stock' => '12,5'])->reason);
        $this->assertStringContainsString('non riconosciuto', $this->import(['sku' => 'N4', 'name' => 'X', 'status' => 'spedito'])->reason);
        $this->assertStringContainsString('negativo', $this->import(['sku' => 'N5', 'name' => 'X', 'price' => '-5'])->reason);

        $this->assertSame(0, Product::count());
    }

    public function test_detects_a_duplicate_sku_within_the_file(): void
    {
        $seen = [];
        $importer = ProductRowImporter::make();

        $importer->import(['sku' => 'E1', 'name' => 'A'], 2, false, $seen);
        $outcome = $importer->import(['sku' => 'E1', 'name' => 'B'], 7, false, $seen);

        $this->assertSame('skipped', $outcome->action);
        $this->assertStringContainsString('duplicato nel file', $outcome->reason);
        $this->assertStringContainsString('riga 2', $outcome->reason);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $outcome = $this->import(['sku' => 'F1', 'name' => 'X', 'price' => '10'], 2, dryRun: true);

        $this->assertSame('created', $outcome->action);
        $this->assertSame(0, Product::count());
    }
}
