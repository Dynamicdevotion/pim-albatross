<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ImportGestionali\Support\ProductRowImporter;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Support\ProductPriceMatrix;
use Modules\Products\Models\Product;
use Tests\TestCase;

/**
 * The two-pass variant path of {@see ProductRowImporter}: containers via
 * importParent(), children via importVariant().
 */
class ProductRowImporterVariantsTest extends TestCase
{
    use RefreshDatabase;

    private PriceList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->list = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        Storage::fake('public');
    }

    private function importer(): ProductRowImporter
    {
        return ProductRowImporter::make();
    }

    private function baseName(Product $product): ?string
    {
        return $product->translate(Locales::baseCode())?->name;
    }

    public function test_creates_a_container_then_a_variant_under_it(): void
    {
        $seen = [];

        $parent = $this->importer()->importParent(['sku' => 'BR', 'name' => 'Bracciale'], 2, true, false, $seen);
        $this->assertSame('created', $parent->action);

        $container = Product::where('sku', 'BR')->sole();
        $this->assertSame('variable', $container->type->value);
        $this->assertSame($container->id, $parent->productId);

        $variant = $this->importer()->importVariant(
            ['sku' => 'BR-S', 'name' => 'Bracciale S', 'price' => '199,00', 'stock' => '3'],
            3,
            $container->id,
            false,
            $seen,
        );

        $this->assertSame('created', $variant->action);

        $row = Product::where('sku', 'BR-S')->sole();
        $this->assertSame('variant', $row->type->value);
        $this->assertSame($container->id, $row->parent_id);
        $this->assertSame(3, $row->stock);
        $this->assertEqualsWithDelta(199.0, (float) $row->prices()->value('price'), 0.001);
    }

    public function test_converts_an_existing_simple_into_a_container_and_strips_its_price_and_stock(): void
    {
        $simple = Product::factory()->create(['sku' => 'RING', 'type' => 'simple', 'stock' => 7, 'weight' => 3]);
        $simple->translations()->create(['language_id' => Locales::idFor(Locales::baseCode()), 'name' => 'Anello']);
        ProductPriceMatrix::write($simple, [['price_list_id' => $this->list->id, 'price' => 50]]);
        $this->assertSame(1, $simple->prices()->count());

        $seen = [];
        $outcome = $this->importer()->importParent(
            ['sku' => 'RING', 'name' => 'Anello Fascetta'],
            4,
            true,
            true,
            $seen,
        );

        $this->assertSame('updated', $outcome->action);
        $this->assertSame($simple->id, $outcome->productId);
        $this->assertStringContainsString('convertito in prodotto a varianti', implode(' ', $outcome->warnings));

        $simple->refresh();
        $this->assertSame('variable', $simple->type->value);
        $this->assertNull($simple->stock);
        $this->assertNull($simple->weight);
        $this->assertSame(0, $simple->prices()->count());
        $this->assertSame('Anello Fascetta', $this->baseName($simple));
    }

    public function test_conversion_is_blocked_when_update_existing_is_off(): void
    {
        $simple = Product::factory()->create(['sku' => 'RING', 'type' => 'simple']);

        $seen = [];
        $outcome = $this->importer()->importParent(['sku' => 'RING', 'name' => 'Anello'], 4, true, false, $seen);

        $this->assertSame('skipped', $outcome->action);
        $this->assertStringContainsString('non è stato convertito', $outcome->reason);
        $this->assertSame('simple', $simple->fresh()->type->value);
    }

    public function test_an_existing_variable_container_is_reused_untouched_when_update_is_off(): void
    {
        $seen = [];
        $created = $this->importer()->importParent(['sku' => 'BR', 'name' => 'Bracciale'], 2, true, false, $seen);
        $containerId = $created->productId;

        $seen = [];
        $reused = $this->importer()->importParent(['sku' => 'BR', 'name' => 'Nome diverso'], 9, true, false, $seen);

        $this->assertSame('unchanged', $reused->action);
        $this->assertSame($containerId, $reused->productId);
        $this->assertSame('Bracciale', $this->baseName(Product::find($containerId)));
    }

    public function test_a_new_variant_with_no_name_inherits_the_parent_name(): void
    {
        $seen = [];
        $parent = $this->importer()->importParent(['sku' => 'BR', 'name' => 'Bracciale Tennis'], 2, true, false, $seen);

        $variant = $this->importer()->importVariant(['sku' => 'BR-M', 'stock' => '2'], 3, $parent->productId, false, $seen);

        $this->assertSame('created', $variant->action);
        $this->assertSame([], $variant->warnings);
        $this->assertSame('Bracciale Tennis', $this->baseName(Product::where('sku', 'BR-M')->sole()));
    }

    public function test_an_implied_parent_with_no_existing_product_is_not_found(): void
    {
        $seen = [];
        $outcome = $this->importer()->importParent(['sku' => 'NOPE'], 6, false, true, $seen);

        $this->assertSame('skipped', $outcome->action);
        $this->assertStringContainsString('non trovato', $outcome->reason);
    }

    public function test_a_parent_that_is_already_a_variant_is_rejected(): void
    {
        $seen = [];
        $parent = $this->importer()->importParent(['sku' => 'BR', 'name' => 'Bracciale'], 2, true, false, $seen);
        $this->importer()->importVariant(['sku' => 'BR-S', 'name' => 'S'], 3, $parent->productId, false, $seen);

        $outcome = $this->importer()->importParent(['sku' => 'BR-S'], 9, false, false, $seen);

        $this->assertSame('skipped', $outcome->action);
        $this->assertStringContainsString('già una variante', $outcome->reason);
    }
}
