<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Filament\Resources\Products\Pages\EditProduct;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ProductRelatedProductsTest extends TestCase
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

    // ---- form: save / load round trip ---------------------------------------

    public function test_create_form_saves_upsells_and_cross_sells(): void
    {
        $a = $this->product('REL-A');
        $b = $this->product('REL-B');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'type' => 'simple',
                'sku' => 'REL-MAIN',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'Principale']],
                'upsell_ids' => [$a->id],
                'cross_sell_ids' => [$b->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'REL-MAIN')->sole();
        $this->assertEqualsCanonicalizing([$a->id], $product->upsells->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$b->id], $product->crossSells->pluck('id')->all());
    }

    public function test_edit_form_prefills_and_updates_upsells_and_cross_sells(): void
    {
        $a = $this->product('REL-C');
        $b = $this->product('REL-D');
        $c = $this->product('REL-E');
        $main = $this->product('REL-MAIN2');
        $main->upsells()->sync([$a->id]);
        $main->crossSells()->sync([$b->id]);

        $component = Livewire::test(EditProduct::class, ['record' => $main->getKey()])
            ->assertFormSet(['upsell_ids' => [$a->id], 'cross_sell_ids' => [$b->id]]);

        $component
            ->fillForm(['upsell_ids' => [$c->id], 'cross_sell_ids' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $main->refresh();
        $this->assertEqualsCanonicalizing([$c->id], $main->upsells->pluck('id')->all());
        $this->assertEqualsCanonicalizing([], $main->crossSells->pluck('id')->all());
    }

    // ---- defensive filtering at the save boundary ---------------------------

    public function test_saving_rejects_a_variant_id_even_if_it_reaches_the_form_state(): void
    {
        $parent = Product::factory()->variable()->create(['sku' => 'REL-VARP']);
        $parent->translations()->create(['locale' => 'it', 'name' => 'REL-VARP']);
        $variant = Product::factory()->variantOf($parent)->create(['sku' => 'REL-VARP-V1']);
        $main = $this->product('REL-MAIN3');

        Livewire::test(EditProduct::class, ['record' => $main->getKey()])
            // bypasses the picker's own exclusion to prove the save-time guard
            ->fillForm(['upsell_ids' => [$variant->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $main->fresh()->upsells->pluck('id')->all());
    }

    public function test_saving_rejects_the_products_own_id(): void
    {
        $main = $this->product('REL-MAIN4');

        Livewire::test(EditProduct::class, ['record' => $main->getKey()])
            ->fillForm(['upsell_ids' => [$main->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $main->fresh()->upsells->pluck('id')->all());
    }

    // ---- cascade on delete ---------------------------------------------------

    public function test_deleting_a_related_product_removes_the_pivot_rows(): void
    {
        $related = $this->product('REL-F');
        $main = $this->product('REL-MAIN5');
        $main->upsells()->sync([$related->id]);
        $main->crossSells()->sync([$related->id]);

        $related->delete();

        $this->assertDatabaseCount('product_upsells', 0);
        $this->assertDatabaseCount('product_cross_sells', 0);
    }

    public function test_deleting_the_owning_product_removes_its_pivot_rows(): void
    {
        $related = $this->product('REL-G');
        $main = $this->product('REL-MAIN6');
        $main->upsells()->sync([$related->id]);

        $main->delete();

        $this->assertDatabaseCount('product_upsells', 0);
    }

    // ---- search excludes self and variants ----------------------------------

    public function test_picker_search_excludes_the_product_itself_and_any_variant(): void
    {
        $main = $this->product('REL-SELF');
        $simple = $this->product('REL-OTHER');
        $parent = Product::factory()->variable()->create(['sku' => 'REL-VARP2']);
        $parent->translations()->create(['locale' => 'it', 'name' => 'REL-VARP2']);
        $variant = Product::factory()->variantOf($parent)->create(['sku' => 'REL-VARIANT']);
        $variant->translations()->create(['locale' => 'it', 'name' => 'REL-VARIANT']);

        $component = Livewire::test(EditProduct::class, ['record' => $main->getKey()]);
        $form = $component->instance()->form;
        $field = $form->getComponent('upsell_ids');

        $results = $field->getSearchResults('REL');

        $this->assertArrayHasKey($simple->id, $results);
        $this->assertArrayHasKey($parent->id, $results);
        $this->assertArrayNotHasKey($main->id, $results);
        $this->assertArrayNotHasKey($variant->id, $results);
        $this->assertSame('REL-OTHER (REL-OTHER)', $results[$simple->id]);
    }
}
