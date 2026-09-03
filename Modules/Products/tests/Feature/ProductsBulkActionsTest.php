<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ProductsBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function product(string $sku, array $overrides = []): Product
    {
        $product = Product::factory()->create(array_merge(['sku' => $sku], $overrides));
        $product->translations()->create(['locale' => 'it', 'name' => $sku]);

        return $product;
    }

    // ---- bulk change status -------------------------------------------------

    public function test_bulk_change_status_updates_every_selected_product(): void
    {
        $a = $this->product('ST-1', ['status' => 'draft']);
        $b = $this->product('ST-2', ['status' => 'draft']);
        $untouched = $this->product('ST-3', ['status' => 'draft']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkChangeStatus', [$a, $b], ['status' => 'active'])
            ->assertNotified(__('pim.notification.status_bulk_updated', [
                'count' => 2,
                'status' => __('pim.option.status.active'),
            ]));

        $this->assertSame('active', $a->fresh()->status);
        $this->assertSame('active', $b->fresh()->status);
        $this->assertSame('draft', $untouched->fresh()->status);
    }

    public function test_bulk_change_status_to_archived_persists_so_the_next_woosync_run_sees_it(): void
    {
        // This bulk action's only contract with WooSync is that `status`
        // ends up saved through a normal model update() — WooSyncRunner
        // itself (tested separately) reads it on its own next run.
        $a = $this->product('ST-ARCH-1', ['status' => 'active']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkChangeStatus', [$a], ['status' => 'archived']);

        $this->assertSame('archived', $a->fresh()->status);
    }

    // ---- bulk set stock and dimensions --------------------------------------

    public function test_bulk_set_stock_and_dimensions_only_touches_filled_fields(): void
    {
        $a = $this->product('DIM-1', ['stock' => 1, 'weight' => 1, 'length' => 1, 'width' => 1, 'height' => 1]);
        $b = $this->product('DIM-2', ['stock' => 2, 'weight' => 2, 'length' => 2, 'width' => 2, 'height' => 2]);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkSetStockAndDimensions', [$a, $b], [
                'stock' => '10',
                'weight' => '2.5',
                // length / width / height left blank -> untouched
            ]);

        foreach ([$a, $b] as $product) {
            $fresh = $product->fresh();
            $this->assertSame(10, $fresh->stock);
            $this->assertSame('2.500', (string) $fresh->weight);
        }
        $this->assertSame('1.00', (string) $a->fresh()->length);
        $this->assertSame('2.00', (string) $b->fresh()->length);
    }

    public function test_bulk_set_stock_and_dimensions_excludes_variable_products_with_a_note(): void
    {
        $simple = $this->product('DIM-S', ['stock' => 0]);
        $variable = Product::factory()->variable()->create(['sku' => 'DIM-V']);
        $variable->translations()->create(['locale' => 'it', 'name' => 'DIM-V']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkSetStockAndDimensions', [$simple, $variable], ['stock' => '15'])
            ->assertNotified(
                trans_choice('pim.notification.stock_dimensions_bulk_updated', 1, ['count' => 1])
                .', '.trans_choice('pim.notification.stock_dimensions_bulk_excluded', 1, ['count' => 1]),
            );

        $this->assertSame(15, $simple->fresh()->stock);
        $this->assertNull($variable->fresh()->stock);
    }

    public function test_bulk_set_stock_and_dimensions_with_nothing_filled_warns_and_changes_nothing(): void
    {
        $a = $this->product('DIM-NOOP', ['stock' => 3]);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkSetStockAndDimensions', [$a], [])
            ->assertNotified(__('pim.notification.bulk_dimensions_nothing_to_set'));

        $this->assertSame(3, $a->fresh()->stock);
    }

    public function test_bulk_set_stock_and_dimensions_with_only_variable_products_selected_updates_none(): void
    {
        $variable = Product::factory()->variable()->create(['sku' => 'DIM-ONLYV']);
        $variable->translations()->create(['locale' => 'it', 'name' => 'DIM-ONLYV']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulkSetStockAndDimensions', [$variable], ['stock' => '5'])
            ->assertNotified(
                trans_choice('pim.notification.stock_dimensions_bulk_updated', 0, ['count' => 0])
                .', '.trans_choice('pim.notification.stock_dimensions_bulk_excluded', 1, ['count' => 1]),
            );

        $this->assertNull($variable->fresh()->stock);
    }
}
