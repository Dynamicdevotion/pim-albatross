<?php

namespace Modules\WooSync\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Modules\WooSync\Exceptions\WooSyncException;
use Modules\WooSync\Models\WooSyncProductLink;
use Modules\WooSync\Models\WooSyncRun;
use Modules\WooSync\Support\ProductPayload;
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
        Storage::fake('public');
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

    public function test_a_standalone_variant_is_always_skipped_with_a_reason(): void
    {
        $parent = Product::factory()->variable()->create(['sku' => 'VARB-STANDALONE']);
        $parent->translations()->create(['locale' => 'it', 'name' => 'Variabile']);
        $variant = Product::factory()->variantOf($parent)->create(['sku' => 'VARB-STANDALONE-V1']);
        $variant->translations()->create(['locale' => 'it', 'name' => 'Variante']);

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($run = $this->makeRun([$variant->id]));
        $run->refresh();

        $this->assertSame(1, $run->skipped_count);
        $this->assertSame('skipped', $run->items[0]['result']);
        $this->assertSame(__('pim.woosync.skip.variant_standalone'), $run->items[0]['reason']);
        $this->assertSame([], $client->calls);
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

    public function test_an_archived_product_with_a_previous_link_is_trashed_on_woo(): void
    {
        $product = $this->simple('ARCH-2');
        $product->forceFill(['status' => 'archived'])->saveQuietly();
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => 700]);

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->skipped_count);
        $this->assertSame('skipped', $run->items[0]['result']);
        $this->assertSame(__('pim.woosync.skip.archived_trashed'), $run->items[0]['reason']);
        $this->assertContains('deleteProduct:700:trash', $client->calls);
        $this->assertNotContains('updateProduct:700', $client->calls);
        $this->assertNotContains('createProduct', $client->calls);
        // The link is left in place, ready for a later sync to find it again.
        $this->assertSame(700, WooSyncProductLink::firstWhere('product_id', $product->id)->woocommerce_id);
    }

    public function test_an_archived_product_whose_woo_trash_call_fails_still_reports_skipped(): void
    {
        $product = $this->simple('ARCH-3');
        $product->forceFill(['status' => 'archived'])->saveQuietly();
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => 701]);

        $client = new FakeWooClient;
        $client->onDeleteProduct = function (): void {
            throw WooSyncException::unreachable('timeout');
        };

        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame('completed', $run->status, 'a failed trash-push does not fail the whole run');
        $this->assertSame(1, $run->skipped_count);
        $this->assertSame('skipped', $run->items[0]['result']);
        $this->assertSame(__('pim.woosync.skip.archived_trash_failed'), $run->items[0]['reason']);
    }

    public function test_a_product_reactivated_after_being_archived_and_trashed_is_republished_not_recreated(): void
    {
        $product = $this->simple('ARCH-4');
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => 702]);
        $client = new FakeWooClient;
        // Still resolvable by id even in the store's trash — WooCommerce
        // doesn't 404 a GET for a trashed product.
        $client->productsById[702] = ['id' => 702, 'status' => 'trash', 'manage_stock' => true, 'stock_quantity' => 5];

        $product->forceFill(['status' => 'active'])->saveQuietly();
        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame(1, $run->updated_count);
        $this->assertSame('publish', $client->updatePayloads[702]['status']);
        $this->assertNotContains('createProduct', $client->calls);
        $this->assertSame(702, WooSyncProductLink::firstWhere('product_id', $product->id)->woocommerce_id);
    }

    public function test_a_second_sync_with_unchanged_images_omits_them_from_the_update(): void
    {
        $product = $this->simple('IMG-1');
        $product->addMedia(UploadedFile::fake()->image('main.jpg'))->toMediaCollection('main_image');
        $client = new FakeWooClient;

        // First sync: creates, so images are sent and the hash is recorded.
        (new WooSyncRunner($client))->run($this->makeRun([$product->id]));
        $this->assertArrayHasKey('images', $client->createPayloads[0]);
        $link = WooSyncProductLink::firstWhere('product_id', $product->id);
        $this->assertNotNull($link->images_hash);

        // Second sync: nothing about the images changed.
        (new WooSyncRunner($client))->run($this->makeRun([$product->id]));

        $this->assertArrayNotHasKey('images', $client->updatePayloads[$link->woocommerce_id]);
    }

    public function test_adding_an_image_resends_it_on_the_next_sync(): void
    {
        $product = $this->simple('IMG-2');
        $product->addMedia(UploadedFile::fake()->image('main.jpg'))->toMediaCollection('main_image');
        $client = new FakeWooClient;

        (new WooSyncRunner($client))->run($this->makeRun([$product->id]));
        $link = WooSyncProductLink::firstWhere('product_id', $product->id);
        $previousHash = $link->images_hash;

        $product->addMedia(UploadedFile::fake()->image('extra.jpg'))->toMediaCollection('gallery');
        (new WooSyncRunner($client))->run($this->makeRun([$product->id]));

        $this->assertArrayHasKey('images', $client->updatePayloads[$link->woocommerce_id]);
        $this->assertCount(2, $client->updatePayloads[$link->woocommerce_id]['images']);
        $this->assertNotSame($previousHash, $link->fresh()->images_hash);
    }

    public function test_a_recreated_product_resends_images_even_if_the_stored_hash_still_matches(): void
    {
        $product = $this->simple('IMG-3');
        $product->addMedia(UploadedFile::fake()->image('main.jpg'))->toMediaCollection('main_image');
        $link = WooSyncProductLink::create([
            'product_id' => $product->id,
            'woocommerce_id' => 800,
            'images_hash' => ProductPayload::for($product->fresh())->imagesHash(),
        ]);

        $client = new FakeWooClient;
        $client->onGetProduct = function (int $id): array {
            throw WooSyncException::gone('deleted');
        };

        (new WooSyncRunner($client))->run($this->makeRun([$product->id]));

        $this->assertContains('createProduct', $client->calls);
        $this->assertArrayHasKey('images', $client->createPayloads[0]);
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

    // ---- up-sell / cross-sell ------------------------------------------------

    public function test_upsell_and_cross_sell_ids_resolve_to_the_related_products_woocommerce_ids(): void
    {
        $upsell = $this->simple('REL-UP');
        WooSyncProductLink::create(['product_id' => $upsell->id, 'woocommerce_id' => 555]);
        $crossSell = $this->simple('REL-CROSS');
        WooSyncProductLink::create(['product_id' => $crossSell->id, 'woocommerce_id' => 556]);

        $product = $this->simple('REL-MAIN');
        $product->upsells()->sync([$upsell->id]);
        $product->crossSells()->sync([$crossSell->id]);

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($this->makeRun([$product->id]));

        $this->assertSame([555], $client->createPayloads[0]['upsell_ids']);
        $this->assertSame([556], $client->createPayloads[0]['cross_sell_ids']);
    }

    public function test_an_unsynced_related_product_is_left_out_and_noted_but_does_not_block_the_sync(): void
    {
        $neverSynced = $this->simple('REL-NEW'); // no WooSyncProductLink at all
        $product = $this->simple('REL-MAIN2');
        $product->upsells()->sync([$neverSynced->id]);

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertSame('created', $run->items[0]['result']);
        $this->assertArrayNotHasKey('upsell_ids', $client->createPayloads[0]);
        $this->assertStringContainsString('REL-NEW', (string) $run->items[0]['reason']);
        $this->assertStringContainsString('up-sell', (string) $run->items[0]['reason']);
    }

    public function test_a_related_product_with_a_link_but_no_woocommerce_id_is_also_left_out(): void
    {
        $notYetPushed = $this->simple('REL-PENDING');
        WooSyncProductLink::create(['product_id' => $notYetPushed->id]); // link row exists, woocommerce_id still null
        $product = $this->simple('REL-MAIN3');
        $product->crossSells()->sync([$notYetPushed->id]);

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($run = $this->makeRun([$product->id]));
        $run->refresh();

        $this->assertArrayNotHasKey('cross_sell_ids', $client->createPayloads[0]);
        $this->assertStringContainsString('cross-sell', (string) $run->items[0]['reason']);
    }

    public function test_a_product_with_no_upsells_or_cross_sells_omits_both_fields(): void
    {
        $product = $this->simple('REL-NONE');

        $client = new FakeWooClient;
        (new WooSyncRunner($client))->run($this->makeRun([$product->id]));

        $this->assertArrayNotHasKey('upsell_ids', $client->createPayloads[0]);
        $this->assertArrayNotHasKey('cross_sell_ids', $client->createPayloads[0]);
    }
}
