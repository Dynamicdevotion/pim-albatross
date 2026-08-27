<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Enums\ProductType;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Filament\Resources\Products\Pages\EditProduct;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ProductResourceVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_product_list_shows_only_top_level_rows(): void
    {
        $simple = Product::factory()->create();
        $variable = Product::factory()->variable()->create();
        $variant = Product::factory()->variantOf($variable)->create();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$simple, $variable])
            ->assertCanNotSeeTableRecords([$variant]);
    }

    public function test_product_list_reports_the_variant_count_for_a_variable(): void
    {
        $variable = Product::factory()->variable()->create();
        Product::factory()->count(2)->variantOf($variable)->create();

        Livewire::test(ListProducts::class)
            ->assertTableColumnStateSet('variants_count', 2, record: $variable)
            ->assertTableColumnFormattedStateSet(
                'variants_count',
                trans_choice('pim.column.variants_count', 2, ['count' => 2]),
                record: $variable,
            );
    }

    public function test_type_filter_narrows_the_list(): void
    {
        $simple = Product::factory()->create();
        $variable = Product::factory()->variable()->create();

        Livewire::test(ListProducts::class)
            ->filterTable('type', ProductType::Variable->value)
            ->assertCanSeeTableRecords([$variable])
            ->assertCanNotSeeTableRecords([$simple]);
    }

    public function test_form_hides_price_and_stock_for_a_variable(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm(['type' => ProductType::Variable->value])
            ->assertFormFieldIsHidden('stock')
            ->assertFormFieldIsHidden('prices')
            ->fillForm(['type' => ProductType::Simple->value])
            ->assertFormFieldIsVisible('stock')
            ->assertFormFieldIsVisible('prices');
    }

    public function test_variable_product_is_saved_without_stock(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => ProductType::Variable->value,
                'sku' => 'VAR-1',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'Maglietta']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Product::where('sku', 'VAR-1')->sole()->stock);
    }

    public function test_type_field_is_locked_once_a_variable_has_variants(): void
    {
        $variable = Product::factory()->variable()->create();
        Product::factory()->variantOf($variable)->create();

        Livewire::test(EditProduct::class, ['record' => $variable->getRouteKey()])
            ->assertFormFieldIsDisabled('type');
    }
}
