<?php

namespace Modules\WooSync\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Models\Product;
use Modules\WooSync\Contracts\WooCommerceClient;
use Modules\WooSync\Exceptions\WooSyncException;
use Modules\WooSync\Models\WooSyncProductLink;
use Modules\WooSync\Providers\WooSyncServiceProvider;
use Modules\WooSync\Tests\Support\FakeWooClient;
use Tests\TestCase;

/**
 * The suite runs with WOOSYNC_ENABLED=true (phpunit.xml), so the
 * `Product::deleting` hook registered by {@see WooSyncServiceProvider}
 * is already wired at boot — these tests exercise it by deleting real
 * `Product` models, not by calling the sync class directly.
 */
class ProductDeletionSyncTest extends TestCase
{
    use RefreshDatabase;

    private FakeWooClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);

        $this->client = new FakeWooClient;
        $this->app->instance(WooCommerceClient::class, $this->client);
    }

    private function simple(string $sku): Product
    {
        $product = Product::factory()->create(['sku' => $sku]);
        $product->translations()->create(['locale' => 'it', 'name' => 'P '.$sku]);

        return $product->fresh();
    }

    public function test_deleting_a_linked_product_propagates_a_trash_delete_to_woo(): void
    {
        $product = $this->simple('DEL-1');
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => 900]);

        $product->delete();

        $this->assertContains('deleteProduct:900:trash', $this->client->calls);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('woosync_product_links', ['product_id' => $product->id]);
    }

    public function test_deleting_a_product_with_no_link_never_calls_woo(): void
    {
        $product = $this->simple('DEL-2');

        $product->delete();

        $this->assertSame([], $this->client->calls);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_deleting_a_product_with_a_link_but_no_woocommerce_id_never_calls_woo(): void
    {
        $product = $this->simple('DEL-3');
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => null]);

        $product->delete();

        $this->assertSame([], $this->client->calls);
        $this->assertDatabaseMissing('woosync_product_links', ['product_id' => $product->id]);
    }

    public function test_a_failed_woo_deletion_does_not_block_the_pim_deletion_and_is_logged(): void
    {
        Log::spy();

        $product = $this->simple('DEL-4');
        WooSyncProductLink::create(['product_id' => $product->id, 'woocommerce_id' => 901]);

        $this->client->onDeleteProduct = function (): void {
            throw WooSyncException::unreachable('timeout');
        };

        $product->delete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('woosync_product_links', ['product_id' => $product->id]);
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $context['woocommerce_id'] === 901
                && $context['sku'] === 'DEL-4');
    }
}
