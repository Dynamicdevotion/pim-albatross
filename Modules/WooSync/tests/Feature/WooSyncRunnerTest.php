<?php

namespace Modules\WooSync\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Modules\WooSync\Exceptions\WooSyncException;
use Modules\WooSync\Models\WooSyncProductLink;
use Modules\WooSync\Models\WooSyncRun;
use Modules\WooSync\Support\WooSyncRunner;
use Modules\WooSync\Tests\Support\FakeWooClient;
use Tests\TestCase;

class WooSyncRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        config(['woosync.request_delay_ms' => 0]);
    }

    private function simple(string $sku): Product
    {
        $product = Product::factory()->create(['sku' => $sku, 'stock' => 0]);
        $product->translations()->create(['locale' => 'it', 'name' => 'P '.$sku]);

        return $product->fresh();
    }

    private function makeRun(array $productIds): WooSyncRun
    {
        return WooSyncRun::create([
            'trigger' => 'bulk',
            'status' => 'pending',
            'product_ids' => $productIds,
            'total' => count($productIds),
        ]);
    }

    public function test_a_new_product_is_created_and_linked_and_stock_is_written_back(): void
    {
        $product = $this->simple('NEW-1');
        $client = new FakeWooClient;

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->created_count);
        $this->assertSame('created', $run->items[0]['result']);
        $this->assertDatabaseHas('woosync_product_links', ['product_id' => $product->id]);
        $this->assertNotNull(WooSyncProductLink::firstWhere('product_id', $product->id)->woocommerce_id);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_a_linked_product_is_updated_not_recreated(): void
    {
        $product = $this->simple('UPD-1');
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => 321]);
        $client = new FakeWooClient;

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->updated_count);
        $this->assertContains('updateProduct:321', $client->calls);
        $this->assertNotContains('createProduct', $client->calls);
        $this->assertSame(12, $product->fresh()->stock);
    }

    public function test_an_unlinked_product_adopts_a_store_product_with_the_same_sku(): void
    {
        $product = $this->simple('ADOPT-1');
        $client = new FakeWooClient;
        $client->productsBySku['ADOPT-1'] = ['id' => 999, 'sku' => 'ADOPT-1'];

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->updated_count);
        $this->assertContains('updateProduct:999', $client->calls);
        $this->assertSame(999, WooSyncProductLink::firstWhere('product_id', $product->id)->woocommerce_id);
    }

    public function test_a_link_that_is_gone_on_the_store_is_recreated(): void
    {
        $product = $this->simple('GONE-1');
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => 42]);

        $client = new FakeWooClient;
        $client->onUpdateProduct = function (int $id): array {
            throw WooSyncException::gone('deleted');
        };

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->created_count);
        $this->assertContains('createProduct', $client->calls);
        $this->assertSame('created', $run->items[0]['result']);
    }

    public function test_variable_and_variant_products_are_skipped_with_a_reason(): void
    {
        $variable = Product::factory()->variable()->create(['sku' => 'VARB']);
        $variable->translations()->create(['locale' => 'it', 'name' => 'Variabile']);

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($run = $this->makeRun([$variable->id]));
        $run->refresh();

        $this->assertSame(1, $run->skipped_count);
        $this->assertSame('skipped', $run->items[0]['result']);
        $this->assertNotContains('createProduct', $client->calls);
    }

    public function test_a_missing_default_price_still_creates_the_product_but_is_noted(): void
    {
        PriceList::query()->delete();
        PriceList::create(['name' => 'Only', 'is_default' => true]);
        $product = $this->simple('NOPRICE');
        $product->prices()->delete();

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->created_count);
        $this->assertNotNull($run->items[0]['reason']);
    }

    public function test_a_rate_limit_stops_the_run_and_keeps_partial_progress(): void
    {
        $a = $this->simple('RL-A');
        $b = $this->simple('RL-B');

        $client = new FakeWooClient;
        $client->onCreateProduct = function (array $payload) use ($client): array {
            if ($payload['sku'] === 'RL-B') {
                throw WooSyncException::rateLimited(5);
            }

            return $payload + ['id' => $client->nextId++, 'manage_stock' => true, 'stock_quantity' => 3];
        };

        (new WooSyncRunner($client))->run($run = $this->makeRun([$a->id, $b->id]));
        $run->refresh();

        $this->assertSame('failed', $run->status);
        $this->assertSame(1, $run->created_count);
        $this->assertCount(1, $run->items);
        $this->assertNotNull($run->error_message);
    }
}
