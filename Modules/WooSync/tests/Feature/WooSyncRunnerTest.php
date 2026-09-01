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

    private function simple(string $sku, int $stock = 0): Product
    {
        $product = Product::factory()->create(['sku' => $sku, 'stock' => $stock]);
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

    public function test_a_new_product_is_created_and_linked_and_pim_stock_is_pushed_on_first_sync(): void
    {
        $product = $this->simple('NEW-1', stock: 5);
        $client = new FakeWooClient;

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->created_count);
        $this->assertSame('created', $run->items[0]['result']);

        $link = WooSyncProductLink::firstWhere('product_id', $product->id);
        $this->assertNotNull($link->woocommerce_id);
        $this->assertSame(5, $client->createPayloads[0]['stock_quantity']);
        $this->assertTrue($client->createPayloads[0]['manage_stock']);
        $this->assertSame(5, $link->last_known_stock);
        // First sync: the PIM already holds the value, nothing written back.
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_a_linked_product_is_updated_not_recreated(): void
    {
        $product = $this->simple('UPD-1', stock: 4);
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => 321]);
        $client = new FakeWooClient;
        $client->productsById[321] = ['id' => 321, 'manage_stock' => true, 'stock_quantity' => 99];

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->updated_count);
        $this->assertContains('getProduct:321', $client->calls);
        $this->assertContains('updateProduct:321', $client->calls);
        $this->assertNotContains('createProduct', $client->calls);
        // No baseline yet -> first sync of an existing product -> PIM value wins.
        $this->assertSame(4, $client->updatePayloads[321]['stock_quantity']);
        $this->assertSame(4, WooSyncProductLink::firstWhere('product_id', $product->id)->last_known_stock);
    }

    public function test_an_unlinked_product_adopts_a_store_product_with_the_same_sku(): void
    {
        $product = $this->simple('ADOPT-1', stock: 2);
        $client = new FakeWooClient;
        $client->productsBySku['ADOPT-1'] = [
            'id' => 999, 'sku' => 'ADOPT-1', 'manage_stock' => true, 'stock_quantity' => 40,
        ];

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->updated_count);
        $this->assertContains('updateProduct:999', $client->calls);
        $this->assertSame(999, WooSyncProductLink::firstWhere('product_id', $product->id)->woocommerce_id);
        $this->assertSame(2, WooSyncProductLink::firstWhere('product_id', $product->id)->last_known_stock);
    }

    public function test_a_link_that_is_gone_on_the_store_is_recreated_as_a_first_sync(): void
    {
        $product = $this->simple('GONE-1', stock: 6);
        WooSyncProductLink::create([
            'product_id' => $product->id, 'woocommerce_id' => 42, 'last_known_stock' => 3,
        ]);

        $client = new FakeWooClient;
        $client->onGetProduct = function (int $id): array {
            throw WooSyncException::gone('deleted');
        };

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->created_count);
        $this->assertContains('createProduct', $client->calls);
        $this->assertSame('created', $run->items[0]['result']);
        $this->assertStringContainsString(__('pim.woosync.stock.recreated'), (string) $run->items[0]['reason']);

        $link = WooSyncProductLink::firstWhere('product_id', $product->id);
        $this->assertSame(6, $client->createPayloads[0]['stock_quantity']);
        $this->assertSame(6, $link->last_known_stock);
        $this->assertNotSame(42, $link->woocommerce_id);
    }

    public function test_delta_sync_reconciles_production_and_sales_that_both_happened(): void
    {
        // Baseline was 10. Since then: +5 produced in the PIM (now 15),
        // 3 sold on the store (now 7). Correct result: 7 + (15 - 10) = 12.
        $product = $this->simple('DELTA-1', stock: 15);
        $link = WooSyncProductLink::create([
            'product_id' => $product->id, 'woocommerce_id' => 500, 'last_known_stock' => 10,
        ]);
        $client = new FakeWooClient;
        $client->productsById[500] = ['id' => 500, 'manage_stock' => true, 'stock_quantity' => 7];

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(12, $client->updatePayloads[500]['stock_quantity'], 'store gets the reconciled value');
        $this->assertSame(12, $product->fresh()->stock, 'PIM gets the reconciled value');
        $this->assertSame(12, $link->fresh()->last_known_stock, 'baseline moves to the reconciled value');

        // Not a naive overwrite in either direction.
        $this->assertNotSame(15, $product->fresh()->stock);
        $this->assertNotSame(7, $product->fresh()->stock);
        $this->assertStringContainsString('12', (string) $run->items[0]['reason']);
    }

    public function test_delta_zero_still_pulls_store_sales_into_the_pim(): void
    {
        // No PIM-side change (still 10), but 4 sold on the store (now 6).
        $product = $this->simple('SALES-1', stock: 10);
        $link = WooSyncProductLink::create([
            'product_id' => $product->id, 'woocommerce_id' => 501, 'last_known_stock' => 10,
        ]);
        $client = new FakeWooClient;
        $client->productsById[501] = ['id' => 501, 'manage_stock' => true, 'stock_quantity' => 6];

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(6, $client->updatePayloads[501]['stock_quantity']);
        $this->assertSame(6, $product->fresh()->stock);
        $this->assertSame(6, $link->fresh()->last_known_stock);
    }

    public function test_first_sync_of_an_existing_managed_store_product_overwrites_with_the_pim_value(): void
    {
        $product = $this->simple('BOOT-1', stock: 4);
        $link = WooSyncProductLink::create([
            'product_id' => $product->id, 'woocommerce_id' => 504, // no last_known_stock
        ]);
        $client = new FakeWooClient;
        $client->productsById[504] = ['id' => 504, 'manage_stock' => true, 'stock_quantity' => 99];

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(4, $client->updatePayloads[504]['stock_quantity']);
        $this->assertSame(4, $link->fresh()->last_known_stock);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertStringContainsString(
            __('pim.woosync.stock.first_sync_overwrite', ['woo' => 99]),
            (string) $run->items[0]['reason'],
        );
    }

    public function test_sync_skips_stock_when_store_stock_management_is_off(): void
    {
        $product = $this->simple('UNMAN-1', stock: 8);
        $link = WooSyncProductLink::create([
            'product_id' => $product->id, 'woocommerce_id' => 502, 'last_known_stock' => 5,
        ]);
        $client = new FakeWooClient;
        $client->productsById[502] = ['id' => 502, 'manage_stock' => false, 'stock_quantity' => null];

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->updated_count);
        $this->assertArrayNotHasKey('stock_quantity', $client->updatePayloads[502]);
        $this->assertArrayNotHasKey('manage_stock', $client->updatePayloads[502]);
        $this->assertSame(8, $product->fresh()->stock, 'PIM stock untouched');
        $this->assertNull($link->fresh()->last_known_stock, 'baseline dropped');
        $this->assertStringContainsString(
            __('pim.woosync.stock.woo_unmanaged'),
            (string) $run->items[0]['reason'],
        );
    }

    public function test_a_negative_delta_result_is_clamped_to_zero_with_a_note(): void
    {
        // Baseline 10; PIM now 8 (delta -2); store now 1. 1 + (-2) = -1 -> 0.
        $product = $this->simple('CLAMP-1', stock: 8);
        $link = WooSyncProductLink::create([
            'product_id' => $product->id, 'woocommerce_id' => 503, 'last_known_stock' => 10,
        ]);
        $client = new FakeWooClient;
        $client->productsById[503] = ['id' => 503, 'manage_stock' => true, 'stock_quantity' => 1];

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(0, $client->updatePayloads[503]['stock_quantity']);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(0, $link->fresh()->last_known_stock);
        $this->assertStringContainsString(__('pim.woosync.stock.clamped'), (string) $run->items[0]['reason']);
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

    public function test_an_archived_product_never_synced_before_is_skipped_with_no_woo_call(): void
    {
        $product = $this->simple('ARCH-1');
        $product->forceFill(['status' => 'archived'])->saveQuietly();

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->skipped_count);
        $this->assertSame('skipped', $run->items[0]['result']);
        $this->assertSame(__('pim.woosync.skip.archived'), $run->items[0]['reason']);
        $this->assertSame([], $client->calls);
    }

    public function test_an_archived_product_with_a_previous_link_is_set_to_draft_on_woo(): void
    {
        $product = $this->simple('ARCH-2');
        $product->forceFill(['status' => 'archived'])->saveQuietly();
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => 700]);

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->skipped_count);
        $this->assertSame('skipped', $run->items[0]['result']);
        $this->assertSame(__('pim.woosync.skip.archived_drafted'), $run->items[0]['reason']);
        $this->assertSame('draft', $client->updatePayloads[700]['status']);
        $this->assertContains('updateProduct:700', $client->calls);
        $this->assertNotContains('createProduct', $client->calls);
    }

    public function test_an_archived_product_whose_woo_draft_update_fails_still_reports_skipped(): void
    {
        $product = $this->simple('ARCH-3');
        $product->forceFill(['status' => 'archived'])->saveQuietly();
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => 701]);

        $client = new FakeWooClient;
        $client->onUpdateProduct = function (): array {
            throw WooSyncException::unreachable('timeout');
        };

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame('completed', $run->status, 'a failed draft-push does not fail the whole run');
        $this->assertSame(1, $run->skipped_count);
        $this->assertSame('skipped', $run->items[0]['result']);
        $this->assertSame(__('pim.woosync.skip.archived_draft_failed'), $run->items[0]['reason']);
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
