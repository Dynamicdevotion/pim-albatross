<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Models\Product;
use Modules\SavedViews\Models\SavedView;
use Tests\TestCase;

class ProductsSavedViewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function product(string $sku): Product
    {
        $product = Product::factory()->create(['sku' => $sku]);
        $product->translations()->create(['locale' => 'it', 'name' => $sku]);

        return $product;
    }

    public function test_saving_a_view_snapshots_the_active_table_filters(): void
    {
        Livewire::test(ListProducts::class)
            ->filterTable('stock', ['level' => 'zero'])
            ->callAction('saveView', ['name' => 'Esauriti']);

        $view = SavedView::query()->sole();

        $this->assertSame('products', $view->resource);
        $this->assertSame('Esauriti', $view->name);
        $this->assertSame('zero', data_get($view->filters, 'stock.level'));
    }

    public function test_selecting_a_saved_view_re_applies_its_filters(): void
    {
        $zero = $this->product('Z');
        $zero->update(['stock' => 0]);
        $plenty = $this->product('K');
        $plenty->update(['stock' => 50]);

        $view = SavedView::create([
            'user_id' => auth()->id(),
            'resource' => 'products',
            'name' => 'Esauriti',
            'filters' => ['stock' => ['level' => 'zero']],
            'columns' => [],
        ]);

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$zero, $plenty])
            ->set('savedViewId', $view->id)
            ->assertCanSeeTableRecords([$zero])
            ->assertCanNotSeeTableRecords([$plenty]);
    }

    public function test_the_active_view_survives_leaving_and_returning_within_the_same_session(): void
    {
        $view = SavedView::create([
            'user_id' => auth()->id(),
            'resource' => 'products',
            'name' => 'Esauriti',
            'filters' => ['stock' => ['level' => 'zero']],
            'columns' => [],
        ]);

        Livewire::test(ListProducts::class)->set('savedViewId', $view->id);

        // a fresh component instance simulates navigating away and back
        // within the same browser session
        Livewire::test(ListProducts::class)
            ->assertSet('savedViewId', $view->id)
            ->assertSet('tableFilters', ['stock' => ['level' => 'zero']]);
    }

    public function test_view_options_are_scoped_to_the_products_key_and_current_user(): void
    {
        SavedView::create([
            'user_id' => auth()->id(),
            'resource' => 'products',
            'name' => 'Mine',
            'filters' => [],
            'columns' => [],
        ]);
        // another user's products view, and my own view on a different screen
        SavedView::factory()->forResource('products')->create(['name' => 'Someone else']);
        SavedView::factory()->for(auth()->user())->forResource('pricing.prices')->create(['name' => 'Prices']);

        $component = Livewire::test(ListProducts::class);

        $this->assertSame(['Mine'], array_values($component->instance()->savedViewOptions()));
    }
}
