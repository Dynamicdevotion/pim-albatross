<?php

namespace Modules\Pricing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Support\ProductPriceMatrix;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ProductPriceMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
    }

    public function test_active_lists_are_default_first_then_by_name(): void
    {
        $wholesale = PriceList::create(['name' => 'Wholesale']);
        $standard = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $archived = PriceList::create(['name' => 'Archived', 'active' => false]);

        $this->assertSame(
            ['Standard', 'Wholesale'],
            ProductPriceMatrix::activeLists()->pluck('name')->all(),
        );
        $this->assertNotContains($archived->id, ProductPriceMatrix::activeLists()->pluck('id')->all());
    }

    public function test_read_items_has_a_row_per_active_list_with_null_when_missing(): void
    {
        $standard = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $wholesale = PriceList::create(['name' => 'Wholesale']);
        PriceList::create(['name' => 'Archived', 'active' => false]);

        $product = Product::factory()->create();
        $product->prices()->create(['price_list_id' => $standard->id, 'price' => 19.90]);

        $items = ProductPriceMatrix::readItems($product);

        $this->assertCount(2, $items); // active lists only
        $this->assertSame($standard->id, $items[0]['price_list_id']);
        $this->assertSame('Standard', $items[0]['price_list_label']);
        $this->assertSame('19.90', (string) $items[0]['price']);
        $this->assertSame($wholesale->id, $items[1]['price_list_id']);
        $this->assertNull($items[1]['price']);
    }

    public function test_write_creates_updates_and_deletes_per_row(): void
    {
        $standard = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $wholesale = PriceList::create(['name' => 'Wholesale']);
        $product = Product::factory()->create();
        $product->prices()->create(['price_list_id' => $standard->id, 'price' => 10]);

        // standard: 10 -> 12.005 (rounds to 12.01); wholesale: none -> 8; (a blank on a
        // list with no price is a no-op)
        ProductPriceMatrix::write($product, [
            ['price_list_id' => $standard->id, 'price' => '12.005'],
            ['price_list_id' => $wholesale->id, 'price' => '8'],
        ]);

        $this->assertSame('12.01', (string) $product->prices()->where('price_list_id', $standard->id)->value('price'));
        $this->assertSame('8.00', (string) $product->prices()->where('price_list_id', $wholesale->id)->value('price'));

        // now clear standard, keep wholesale
        ProductPriceMatrix::write($product, [
            ['price_list_id' => $standard->id, 'price' => ''],
            ['price_list_id' => $wholesale->id, 'price' => '8'],
        ]);

        $this->assertDatabaseMissing('product_prices', ['product_id' => $product->id, 'price_list_id' => $standard->id]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $product->id, 'price_list_id' => $wholesale->id]);
        $this->assertSame(1, $product->prices()->count());
    }

    public function test_write_leaves_inactive_list_prices_untouched(): void
    {
        $standard = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $legacy = PriceList::create(['name' => 'Legacy']);
        $product = Product::factory()->create();
        $product->prices()->create(['price_list_id' => $legacy->id, 'price' => 5]);

        $legacy->update(['active' => false]);

        // editor only ever submits rows for active lists
        ProductPriceMatrix::write($product, [
            ['price_list_id' => $standard->id, 'price' => '9'],
        ]);

        $this->assertDatabaseHas('product_prices', ['product_id' => $product->id, 'price_list_id' => $legacy->id, 'price' => 5.00]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $product->id, 'price_list_id' => $standard->id, 'price' => 9.00]);
    }

    public function test_write_ignores_rows_for_non_active_lists(): void
    {
        $standard = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $inactive = PriceList::create(['name' => 'Off']);
        $inactive->update(['active' => false]);
        $product = Product::factory()->create();

        ProductPriceMatrix::write($product, [
            ['price_list_id' => $inactive->id, 'price' => '99'],
        ]);

        $this->assertDatabaseCount('product_prices', 0);
    }

    public function test_read_items_include_the_sale_price(): void
    {
        $standard = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $product = Product::factory()->create();
        $product->prices()->create(['price_list_id' => $standard->id, 'price' => 20, 'sale_price' => 15]);

        $items = ProductPriceMatrix::readItems($product);

        $this->assertSame('15.00', (string) $items[0]['sale_price']);
    }

    public function test_write_sets_and_clears_the_sale_price_alongside_the_price(): void
    {
        $standard = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $product = Product::factory()->create();

        ProductPriceMatrix::write($product, [
            ['price_list_id' => $standard->id, 'price' => '20', 'sale_price' => '15'],
        ]);

        $this->assertSame('15.00', (string) $product->prices()->where('price_list_id', $standard->id)->value('sale_price'));

        ProductPriceMatrix::write($product, [
            ['price_list_id' => $standard->id, 'price' => '20', 'sale_price' => ''],
        ]);

        $this->assertNull($product->prices()->where('price_list_id', $standard->id)->value('sale_price'));
    }

    public function test_clearing_the_price_clears_the_sale_price_too(): void
    {
        $standard = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $product = Product::factory()->create();
        $product->prices()->create(['price_list_id' => $standard->id, 'price' => 20, 'sale_price' => 15]);

        ProductPriceMatrix::write($product, [
            ['price_list_id' => $standard->id, 'price' => ''],
        ]);

        $this->assertDatabaseMissing('product_prices', ['product_id' => $product->id, 'price_list_id' => $standard->id]);
    }
}
