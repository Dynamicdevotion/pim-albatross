<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Support\Locales;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Filament\Resources\Products\Pages\EditProduct;
use Modules\Products\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ProductSlugAndBarcodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_barcode_is_stored_as_a_plain_column(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'BAR-1',
                'barcode' => '5901234123457',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'Prodotto']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('5901234123457', Product::where('sku', 'BAR-1')->sole()->barcode);
    }

    public function test_a_blank_slug_is_generated_from_the_translated_name(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'SLUG-1',
                'status' => 'draft',
                'translations' => [
                    'it' => ['name' => 'Sedia da Ufficio', 'slug' => ''],
                    'en' => ['name' => 'Office Chair', 'slug' => ''],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'SLUG-1')->sole();
        $this->assertSame('sedia-da-ufficio', $product->translate('it')->slug);
        $this->assertSame('office-chair', $product->translate('en')->slug);
    }

    public function test_a_manually_typed_slug_is_sanitized_and_kept(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'SLUG-2',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'Tavolo', 'slug' => 'Il Mio Tavolo!']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('il-mio-tavolo', Product::where('sku', 'SLUG-2')->sole()->translate('it')->slug);
    }

    public function test_two_products_cannot_share_a_slug_in_the_same_language(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'SLUG-3A',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'Uno', 'slug' => 'stessa-cosa']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'SLUG-3B',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'Due', 'slug' => 'stessa-cosa']],
            ])
            ->call('create')
            ->assertHasFormErrors(['translations.it.slug']);
    }

    public function test_the_same_slug_is_fine_in_a_different_language(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'SLUG-4A',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'Uno', 'slug' => 'condiviso']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'SLUG-4B',
                'status' => 'draft',
                'translations' => [
                    'it' => ['name' => 'Due'],
                    'en' => ['name' => 'Two', 'slug' => 'condiviso'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_auto_generated_slugs_are_deduplicated_when_names_collide(): void
    {
        $first = Product::factory()->create(['sku' => 'SLUG-5A']);
        $first->translations()->create(['locale' => 'it', 'name' => 'Uguale', 'slug' => 'uguale']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'SLUG-5B',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'Uguale']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('uguale-2', Product::where('sku', 'SLUG-5B')->sole()->translate('it')->slug);
    }

    public function test_editing_a_product_can_keep_its_own_slug(): void
    {
        $product = Product::factory()->create(['sku' => 'SLUG-6']);
        $product->translations()->create(['locale' => 'it', 'name' => 'X', 'slug' => 'mio-slug']);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->assertFormSet(['translations.it.slug' => 'mio-slug'])
            ->fillForm(['translations.it.name' => 'X modificato'])
            ->call('save')
            ->assertHasNoFormErrors();

        // the name changed but the slug was submitted unchanged -> kept as-is
        $this->assertSame('mio-slug', $product->fresh()->translate('it')->slug);
    }

    public function test_variant_slug_and_barcode_are_independent_of_the_parent(): void
    {
        $parent = Product::factory()->variable()->create(['sku' => 'VAR-P']);
        $parent->translations()->create(['locale' => 'it', 'name' => 'Contenitore', 'slug' => 'contenitore']);

        Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $parent,
            'pageClass' => EditProduct::class,
        ])
            ->callAction(TestAction::make('create')->table(), data: [
                'sku' => 'VAR-P-1',
                'barcode' => '1112223334445',
                'stock' => 0,
                'translations' => ['it' => ['name' => 'Variante Rossa']],
            ])
            ->assertHasNoErrors();

        $variant = Product::where('sku', 'VAR-P-1')->sole();
        $this->assertSame('1112223334445', $variant->barcode);
        $this->assertSame('variante-rossa', $variant->translate('it')->slug);
    }
}
