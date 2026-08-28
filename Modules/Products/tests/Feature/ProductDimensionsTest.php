<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Enums\ProductType;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Filament\Resources\Products\Pages\EditProduct;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ProductDimensionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_dimension_fields_persist_on_a_simple_product(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => ProductType::Simple->value,
                'sku' => 'DIM-1',
                'status' => 'draft',
                'weight' => 0.75,
                'length' => 30,
                'width' => 20,
                'height' => 10,
                'translations' => ['it' => ['name' => 'Scatola']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'DIM-1')->sole();

        $this->assertSame('0.750', $product->weight);
        $this->assertSame('30.00', $product->length);
        $this->assertSame('20.00', $product->width);
        $this->assertSame('10.00', $product->height);
    }

    public function test_form_hides_the_dimensions_section_for_a_variable(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm(['type' => ProductType::Variable->value])
            ->assertFormFieldIsHidden('weight')
            ->assertFormFieldIsHidden('length')
            ->assertFormFieldIsHidden('width')
            ->assertFormFieldIsHidden('height')
            ->fillForm(['type' => ProductType::Simple->value])
            ->assertFormFieldIsVisible('weight')
            ->assertFormFieldIsVisible('height');
    }

    public function test_a_variable_product_never_keeps_its_own_dimensions(): void
    {
        $variable = Product::factory()->variable()->withDimensions()->create();

        $this->assertNull($variable->fresh()->weight);
        $this->assertNull($variable->fresh()->length);
        $this->assertNull($variable->fresh()->width);
        $this->assertNull($variable->fresh()->height);
    }

    public function test_variant_form_exposes_and_saves_the_dimension_fields(): void
    {
        $parent = Product::factory()->variable()->create(['sku' => 'TSHIRT']);
        $parent->translations()->create(['locale' => 'it', 'name' => 'Maglietta']);

        Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $parent,
            'pageClass' => EditProduct::class,
        ])
            ->callAction(TestAction::make('create')->table(), data: [
                'sku' => 'TSHIRT-M',
                'stock' => 3,
                'weight' => 0.25,
                'length' => 25,
                'width' => 18,
                'height' => 2,
                'translations' => ['it' => ['name' => 'Maglietta M']],
            ])
            ->assertHasNoErrors();

        $variant = $parent->variants()->sole();

        $this->assertSame('0.250', $variant->weight);
        $this->assertSame('25.00', $variant->length);
        $this->assertSame('18.00', $variant->width);
        $this->assertSame('2.00', $variant->height);
    }

    public function test_dimension_columns_exist_on_the_list_but_are_hidden_by_default(): void
    {
        Livewire::test(ListProducts::class)
            ->assertTableColumnExists('weight')
            ->assertTableColumnExists('length')
            ->assertTableColumnExists('width')
            ->assertTableColumnExists('height')
            ->assertCanNotRenderTableColumn('weight')
            ->assertCanNotRenderTableColumn('height')
            ->assertCanRenderTableColumn('sku');
    }
}
