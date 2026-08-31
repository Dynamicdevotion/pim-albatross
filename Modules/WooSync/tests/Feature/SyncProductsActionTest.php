<?php

namespace Modules\WooSync\Tests\Feature;

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Models\Product;
use Modules\WooSync\Contracts\WooCommerceClient;
use Modules\WooSync\Jobs\RunWooSync;
use Modules\WooSync\Models\WooSyncRun;
use Modules\WooSync\Models\WooSyncSetting;
use Modules\WooSync\Tests\Support\FakeWooClient;
use Tests\TestCase;

class SyncProductsActionTest extends TestCase
{
    use RefreshDatabase;

    private FakeWooClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->client = new FakeWooClient;
        $this->app->instance(WooCommerceClient::class, $this->client);

        config(['woosync.request_delay_ms' => 0]);
    }

    private function configureConnection(): void
    {
        WooSyncSetting::current()->update([
            'store_url' => 'https://shop.example.com',
            'consumer_key' => 'ck',
            'consumer_secret' => 'cs',
        ]);
    }

    private function product(string $sku): Product
    {
        $product = Product::factory()->create(['sku' => $sku]);
        $product->translations()->create(['locale' => 'it', 'name' => 'P '.$sku]);

        return $product->fresh();
    }

    public function test_the_row_action_runs_a_sync_inline_and_redirects_to_the_report(): void
    {
        $this->configureConnection();
        $product = $this->product('ROW-1');

        Queue::fake();

        Livewire::test(ListProducts::class)
            ->callAction(TestAction::make('woosync')->table($product));

        Queue::assertNothingPushed();
        $run = WooSyncRun::sole();
        $this->assertSame('single', $run->trigger);
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->created_count);
        $this->assertContains('createProduct', $this->client->calls);
    }

    public function test_a_large_bulk_selection_is_queued(): void
    {
        $this->configureConnection();
        config(['woosync.inline_max_products' => 1]);

        $products = collect(['B-1', 'B-2', 'B-3'])->map(fn (string $sku) => $this->product($sku));

        Queue::fake();

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('woosync', $products->pluck('id')->all());

        Queue::assertPushed(RunWooSync::class, 1);
        $this->assertSame('bulk', WooSyncRun::sole()->trigger);
        $this->assertSame('pending', WooSyncRun::sole()->status);
    }

    public function test_the_action_is_hidden_until_the_connection_is_configured(): void
    {
        $product = $this->product('HID-1');

        Livewire::test(ListProducts::class)
            ->assertActionHidden(TestAction::make('woosync')->table($product));
    }
}
