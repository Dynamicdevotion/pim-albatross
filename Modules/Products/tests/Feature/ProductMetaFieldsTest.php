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

class ProductMetaFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_meta_fields_are_stored_per_language_through_the_form(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'META-1',
                'status' => 'draft',
                'translations' => [
                    'it' => [
                        'name' => 'Sedia',
                        'meta_title' => 'Sedia di design',
                        'meta_description' => 'La sedia perfetta per il tuo ufficio.',
                    ],
                    'en' => [
                        'name' => 'Chair',
                        'meta_title' => 'Design chair',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'META-1')->sole();

        $this->assertSame('Sedia di design', $product->translate('it')->meta_title);
        $this->assertSame('La sedia perfetta per il tuo ufficio.', $product->translate('it')->meta_description);
        $this->assertSame('Design chair', $product->translate('en')->meta_title);
        $this->assertNull($product->translate('en')->meta_description);
    }

    public function test_the_edit_form_loads_the_meta_fields_back(): void
    {
        $product = Product::factory()->create(['sku' => 'META-2']);
        $product->translations()->create([
            'locale' => Locales::baseCode(),
            'name' => 'Tavolo',
            'meta_title' => 'Tavolo in legno',
            'meta_description' => 'Massello, fatto a mano.',
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->assertFormSet([
                'translations.'.Locales::baseCode().'.meta_title' => 'Tavolo in legno',
                'translations.'.Locales::baseCode().'.meta_description' => 'Massello, fatto a mano.',
            ]);
    }

    public function test_a_blank_meta_field_is_stored_as_null_and_does_not_create_a_row_on_its_own(): void
    {
        $product = Product::factory()->create(['sku' => 'META-3']);
        $product->translations()->create([
            'locale' => Locales::baseCode(),
            'name' => 'X',
            'meta_title' => 'Vecchio',
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['translations.'.Locales::baseCode().'.meta_title' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($product->fresh()->translate(Locales::baseCode())->meta_title);

        // an EN tab with only a meta_title and no name -> no row created
        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['translations.en.meta_title' => 'Only meta, no name'])
            ->call('save');

        $this->assertNull($product->fresh()->translate('en'));
    }

    public function test_meta_fields_apply_to_variants(): void
    {
        $parent = Product::factory()->variable()->create(['sku' => 'META-VAR']);
        $parent->translations()->create(['locale' => Locales::baseCode(), 'name' => 'Contenitore']);

        Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $parent,
            'pageClass' => EditProduct::class,
        ])
            ->callAction(TestAction::make('create')->table(), data: [
                'sku' => 'META-VAR-1',
                'stock' => 0,
                'translations' => [
                    Locales::baseCode() => [
                        'name' => 'Variante rossa',
                        'meta_title' => 'Variante rossa SEO',
                    ],
                ],
            ])
            ->assertHasNoErrors();

        $variant = Product::where('sku', 'META-VAR-1')->sole();
        $this->assertSame('Variante rossa SEO', $variant->translate(Locales::baseCode())->meta_title);
    }
}
