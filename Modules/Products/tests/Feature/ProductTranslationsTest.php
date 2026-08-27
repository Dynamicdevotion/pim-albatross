<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\ProductTranslationSeeder;
use Modules\Localization\Enums\Locale;
use Modules\Localization\Models\ProductTranslation;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Filament\Resources\Products\Pages\EditProduct;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ProductTranslationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_translate_returns_the_row_or_null_without_fallback(): void
    {
        $product = Product::create(['sku' => 'T-1', 'status' => 'draft']);
        $product->translations()->create(['locale' => 'it', 'name' => 'Sedia']);

        $this->assertSame('Sedia', $product->translate('it')->name);
        $this->assertSame('Sedia', $product->translate(Locale::Italian)->name);
        $this->assertNull($product->translate('en'));
        $this->assertNull($product->translate(Locale::German));
    }

    public function test_deleting_a_product_cascades_to_translations(): void
    {
        $product = Product::create(['sku' => 'T-2', 'status' => 'draft']);
        $product->translations()->create(['locale' => 'it', 'name' => 'Tavolo']);

        $product->delete();

        $this->assertDatabaseCount('product_translations', 0);
    }

    public function test_create_page_persists_only_locales_with_a_name(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku' => 'T-CREATE',
                'status' => 'draft',
                'translations' => [
                    'it' => ['name' => 'Lampada', 'description' => '<p>Lampada da tavolo</p>'],
                    'en' => ['name' => 'Lamp'],
                    'es' => ['name' => ''],
                    'fr' => ['name' => '   '],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'T-CREATE')->sole();

        $this->assertEqualsCanonicalizing(
            ['it', 'en'],
            $product->translations->pluck('locale')->all(),
        );
        $this->assertSame('<p>Lampada da tavolo</p>', $product->translate('it')->description);
        $this->assertNull($product->translate('en')->description);
        $this->assertNull($product->translate('es'));
        $this->assertNull($product->translate('fr'));
    }

    public function test_edit_page_prefills_then_updates_and_prunes_translations(): void
    {
        $product = Product::create(['sku' => 'T-EDIT', 'status' => 'draft']);
        $product->translations()->create(['locale' => 'it', 'name' => 'Tavolo']);
        $product->translations()->create(['locale' => 'en', 'name' => 'Table']);

        $component = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);

        $state = $component->get('data');
        $this->assertSame('Tavolo', $state['translations']['it']['name']);
        $this->assertSame('Table', $state['translations']['en']['name']);

        $component
            ->fillForm([
                'translations' => [
                    'it' => ['name' => 'Tavolo XL'],
                    'en' => ['name' => ''],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();

        $this->assertSame('Tavolo XL', $product->translate('it')->name);
        $this->assertNull($product->translate('en'));
    }

    public function test_factory_builds_a_valid_translation_for_a_given_locale(): void
    {
        $translation = ProductTranslation::factory()
            ->forLocale(Locale::English)
            ->create();

        $this->assertDatabaseHas('product_translations', [
            'id' => $translation->id,
            'locale' => 'en',
        ]);
        $this->assertInstanceOf(Product::class, $translation->product);
        $this->assertNotEmpty($translation->name);

        $this->assertNull(
            ProductTranslation::factory()->withoutDescription()->create()->description,
        );
    }

    public function test_seeder_translates_every_product_and_is_idempotent(): void
    {
        Product::factory()->count(3)->create();

        (new ProductTranslationSeeder())->run();
        $this->assertDatabaseCount('product_translations', 6); // 3 products x (it, en)

        (new ProductTranslationSeeder())->run();
        $this->assertDatabaseCount('product_translations', 6); // no duplicates on re-run
    }

    public function test_product_list_reports_translated_locales_and_filters_by_missing_one(): void
    {
        $itEn = Product::factory()->create();
        $itEn->translations()->create(['locale' => 'it', 'name' => 'A']);
        $itEn->translations()->create(['locale' => 'en', 'name' => 'A EN']);

        $itOnly = Product::factory()->create();
        $itOnly->translations()->create(['locale' => 'it', 'name' => 'B']);

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$itEn, $itOnly])
            ->assertTableColumnStateSet('translated_locales', ['IT', 'EN'], record: $itEn)
            ->assertTableColumnStateSet('translated_locales', ['IT'], record: $itOnly)
            ->filterTable('missing_translation', 'en')
            ->assertCanSeeTableRecords([$itOnly])
            ->assertCanNotSeeTableRecords([$itEn]);
    }
}
