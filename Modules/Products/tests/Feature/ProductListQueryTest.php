<?php

namespace Modules\Products\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Modules\Products\Support\ProductListQuery;
use Tests\TestCase;

/**
 * ProductListQuery is the shared source of truth behind both the products
 * list filters and the export. The list page has its own coverage in
 * ProductsTableFiltersTest; this locks the standalone entry point the export
 * uses.
 */
class ProductListQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
    }

    private function product(string $sku, string $name, array $overrides = []): Product
    {
        $product = Product::factory()->create(array_merge(['sku' => $sku], $overrides));
        $product->translations()->create(['locale' => 'it', 'name' => $name]);

        return $product;
    }

    public function test_base_scope_returns_top_level_products_only(): void
    {
        $parent = Product::factory()->variable()->create(['sku' => 'V']);
        Product::factory()->variantOf($parent)->create(['sku' => 'V-1']);
        $this->product('S', 'Simple');

        $skus = ProductListQuery::for([])->pluck('sku')->all();

        $this->assertEqualsCanonicalizing(['V', 'S'], $skus);
    }

    public function test_it_applies_search_type_status_and_price_filters_together(): void
    {
        $std = PriceList::create(['name' => 'Standard', 'is_default' => true]);

        $match = $this->product('MATCH', 'Cappello rosso', ['status' => 'active']);
        $match->prices()->create(['price_list_id' => $std->id, 'price' => 30]);

        $wrongStatus = $this->product('DRAFT', 'Cappello rosso', ['status' => 'draft']);
        $wrongStatus->prices()->create(['price_list_id' => $std->id, 'price' => 30]);

        $wrongName = $this->product('OTHER', 'Sciarpa', ['status' => 'active']);
        $wrongName->prices()->create(['price_list_id' => $std->id, 'price' => 30]);

        $noPrice = $this->product('NOPRICE', 'Cappello rosso', ['status' => 'active']);

        $skus = ProductListQuery::for([
            'search' => ['term' => 'cappello'],
            'status' => ['value' => 'active'],
            'price' => ['price_list_id' => $std->id, 'presence' => 'with'],
        ])->pluck('sku')->all();

        $this->assertSame(['MATCH'], $skus);
    }

    public function test_missing_translation_per_language_and_any(): void
    {
        $activeCodes = Locales::activeCodes();

        $complete = Product::factory()->create(['sku' => 'FULL']);
        foreach ($activeCodes as $code) {
            $complete->translations()->create(['locale' => $code, 'name' => "Full {$code}"]);
        }

        $itOnly = $this->product('IT-ONLY', 'Solo italiano'); // base language only
        $none = Product::factory()->create(['sku' => 'NONE']); // no translations at all

        // Per-language: only products with no row in that specific language.
        $missingEn = ProductListQuery::for(['missing_translation' => ['value' => 'en']])
            ->pluck('sku')->all();
        $this->assertEqualsCanonicalizing(['IT-ONLY', 'NONE'], $missingEn);

        // Any active language: fewer translations than active languages.
        $missingAny = ProductListQuery::for(['missing_translation' => ['value' => '*']])
            ->pluck('sku')->all();
        $this->assertEqualsCanonicalizing(['IT-ONLY', 'NONE'], $missingAny);
        $this->assertNotContains('FULL', $missingAny);
    }
}
