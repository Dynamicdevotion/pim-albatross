<?php

namespace Modules\Pricing\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Database\Seeders\PricingSeeder;
use Modules\Pricing\Database\Seeders\ProductPriceSeeder;
use Modules\Pricing\Filament\Pages\ManagePrices;
use Modules\Pricing\Filament\Resources\PriceLists\Pages\CreatePriceList;
use Modules\Pricing\Filament\Resources\PriceLists\Pages\ListPriceLists;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Models\ProductPrice;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use RuntimeException;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function product(string $name, string $sku): Product
    {
        $product = Product::create(['sku' => $sku, 'status' => 'draft']);
        $product->translations()->create(['locale' => 'it', 'name' => $name]);

        return $product;
    }

    public function test_exactly_one_default_price_list(): void
    {
        $a = PriceList::create(['name' => 'A', 'is_default' => true]);
        $b = PriceList::create(['name' => 'B', 'is_default' => true]);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
        $this->assertSame(1, PriceList::query()->where('is_default', true)->count());

        $this->expectException(RuntimeException::class);
        $b->delete();
    }

    public function test_prices_cascade_on_delete(): void
    {
        $list = PriceList::create(['name' => 'L']);
        $p = $this->product('Sedia', 'C-1');
        $p->prices()->create(['price_list_id' => $list->id, 'price' => 19.90]);

        $this->assertSame('19.90', (string) $p->fresh()->prices->first()->price);

        $list->delete();
        $this->assertDatabaseCount('product_prices', 0);

        $list2 = PriceList::create(['name' => 'L2']);
        $p->prices()->create(['price_list_id' => $list2->id, 'price' => 5]);
        $p->delete();
        $this->assertDatabaseCount('product_prices', 0);
    }

    public function test_seeder_creates_standard_default_and_is_idempotent(): void
    {
        (new PricingSeeder())->run();
        (new PricingSeeder())->run();

        $this->assertSame(1, PriceList::query()->count());
        $standard = PriceList::query()->sole();
        $this->assertSame('Standard', $standard->name);
        $this->assertTrue($standard->is_default);
        $this->assertTrue($standard->active);
    }

    public function test_create_price_list_populates_from_another_with_a_percentage(): void
    {
        $source = PriceList::create(['name' => 'Base']);
        $p1 = $this->product('Uno', 'P-1');
        $p2 = $this->product('Due', 'P-2');
        $source->prices()->create(['product_id' => $p1->id, 'price' => 100]);
        $source->prices()->create(['product_id' => $p2->id, 'price' => 50]);

        Livewire::test(CreatePriceList::class)
            ->fillForm([
                'name' => 'Wholesale',
                'active' => true,
                'source_price_list_id' => $source->id,
                'adjustment_percent' => -15,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $wholesale = PriceList::query()->where('name', 'Wholesale')->sole();
        $this->assertEqualsCanonicalizing(
            ['85.00', '42.50'],
            $wholesale->prices->pluck('price')->map(fn ($p) => (string) $p)->all(),
        );
    }

    public function test_set_default_action_moves_the_flag(): void
    {
        $a = PriceList::create(['name' => 'A', 'is_default' => true]);
        $b = PriceList::create(['name' => 'B', 'active' => false]);

        Livewire::test(ListPriceLists::class)
            ->callTableAction('setDefault', $b);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
        $this->assertTrue($b->fresh()->active); // forced active
    }

    public function test_manage_prices_grid_saves_and_clears_cells_in_a_batch(): void
    {
        $list = PriceList::create(['name' => 'Std', 'is_default' => true]);
        $a = $this->product('Tavolo', 'T-1');
        $b = $this->product('Sedia', 'T-2');

        $component = Livewire::test(ManagePrices::class)->set('priceListId', $list->id);

        $component->call('saveCells', [
            ['product_id' => $a->id, 'price' => '29.9'],
            ['product_id' => $b->id, 'price' => '5'],
        ]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $a->id, 'price_list_id' => $list->id, 'price' => 29.90]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $b->id, 'price' => 5.00]);

        $component->call('saveCells', [['product_id' => $a->id, 'price' => '']]);
        $this->assertDatabaseMissing('product_prices', ['product_id' => $a->id]);
        $this->assertDatabaseCount('product_prices', 1);
    }

    public function test_manage_prices_rows_respect_search_price_and_category_filters(): void
    {
        $list = PriceList::create(['name' => 'Std', 'is_default' => true]);
        $shirt = $this->product('Maglietta', 'CAT-1');
        $shoes = $this->product('Scarpe', 'CAT-2');
        $plain = $this->product('Altro', 'OTH-1');
        $shirt->prices()->create(['price_list_id' => $list->id, 'price' => 10]);

        $clothing = Taxonomy::factory()->named('Categoria')->create()->terms()->create(['slug' => 'abbigliamento']);
        $clothing->translations()->create(['locale' => 'it', 'name' => 'Abbigliamento']);
        $shirt->taxonomyTerms()->attach($clothing->id);
        $shoes->taxonomyTerms()->attach($clothing->id);

        $page = Livewire::test(ManagePrices::class)->set('priceListId', $list->id)->instance();

        $skus = fn () => collect($page->rows())->pluck('sku')->sort()->values()->all();

        $page->search = 'Magli';
        $this->assertSame(['CAT-1'], $skus());

        $page->search = '';
        $page->hasPrice = 'no';
        $this->assertSame(['CAT-2', 'OTH-1'], $skus());

        $page->hasPrice = null;
        $page->categoryTermId = $clothing->id;
        $this->assertSame(['CAT-1', 'CAT-2'], $skus());
    }

    public function test_manage_prices_selection_percentage_bulk_action(): void
    {
        $list = PriceList::create(['name' => 'Std', 'is_default' => true]);
        $other = PriceList::create(['name' => 'Other']);
        $priced = $this->product('Uno', 'B-1');
        $unpriced = $this->product('Due', 'B-2');
        $priced->prices()->create(['price_list_id' => $list->id, 'price' => 100]);
        $priced->prices()->create(['price_list_id' => $other->id, 'price' => 100]);

        Livewire::test(ManagePrices::class)
            ->set('priceListId', $list->id)
            ->set('selectedProductIds', [$priced->id, $unpriced->id])
            ->callAction('adjustSelection', ['percent' => 10]);

        $this->assertDatabaseHas('product_prices', ['product_id' => $priced->id, 'price_list_id' => $list->id, 'price' => 110.00]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $priced->id, 'price_list_id' => $other->id, 'price' => 100.00]); // other list untouched
        $this->assertDatabaseMissing('product_prices', ['product_id' => $unpriced->id]); // no base price -> skipped
    }

    public function test_price_adjuster_category_targets_one_list_and_the_category_tree(): void
    {
        $list = PriceList::create(['name' => 'Std', 'is_default' => true]);
        $other = PriceList::create(['name' => 'Other']);

        $taxonomy = Taxonomy::factory()->named('Categoria')->create();
        $clothing = $taxonomy->terms()->create(['slug' => 'abbigliamento']);
        $clothing->translations()->create(['locale' => 'it', 'name' => 'Abbigliamento']);
        $shirts = $taxonomy->terms()->create(['slug' => 'magliette', 'parent_id' => $clothing->id]);
        $shirts->translations()->create(['locale' => 'it', 'name' => 'Magliette']);

        $inTree = $this->product('T-shirt', 'C-1');   // under the child term
        $inTree->taxonomyTerms()->attach($shirts->id);
        $inTree->prices()->create(['price_list_id' => $list->id, 'price' => 100]);
        $inTree->prices()->create(['price_list_id' => $other->id, 'price' => 100]);

        $outside = $this->product('Altro', 'C-2');
        $outside->prices()->create(['price_list_id' => $list->id, 'price' => 100]);

        $changed = \Modules\Pricing\Support\PriceAdjuster::adjustCategory($clothing->id, $list->id, -50);

        $this->assertSame(1, $changed);
        $this->assertDatabaseHas('product_prices', ['product_id' => $inTree->id, 'price_list_id' => $list->id, 'price' => 50.00]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $inTree->id, 'price_list_id' => $other->id, 'price' => 100.00]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $outside->id, 'price_list_id' => $list->id, 'price' => 100.00]);
    }

    public function test_manage_prices_applies_a_saved_view(): void
    {
        $list = PriceList::create(['name' => 'Std', 'is_default' => true]);
        $user = User::query()->first();

        $view = \Modules\SavedViews\Models\SavedView::create([
            'user_id' => $user->id,
            'resource' => 'pricing.prices',
            'name' => 'Missing only',
            'filters' => ['search' => '', 'hasPrice' => 'no', 'categoryTermId' => null],
            'columns' => ['name', 'sku'],
        ]);

        Livewire::test(ManagePrices::class)
            ->set('priceListId', $list->id)
            ->set('savedViewId', $view->id)
            ->assertSet('hasPrice', 'no')
            ->assertSet('visibleColumns', ['name', 'sku']);
    }

    public function test_product_form_repeater_writes_per_list_prices(): void
    {
        $a = PriceList::create(['name' => 'A', 'is_default' => true]);
        $b = PriceList::create(['name' => 'B']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku' => 'PR-FORM',
                'status' => 'draft',
                'translations' => ['it' => ['name' => 'Prezzato']],
                'prices' => [
                    ['price_list_id' => $a->id, 'price' => '11.00'],
                    ['price_list_id' => $b->id, 'price' => '9.50'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('sku', 'PR-FORM')->sole();
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $product->prices->pluck('price_list_id')->all(),
        );
    }

    public function test_example_price_seeder_prices_every_product_and_is_idempotent(): void
    {
        $this->product('Uno', 'S-1');
        $this->product('Due', 'S-2');

        (new ProductPriceSeeder())->run();

        // 2 products x 1 active list (Standard, created by the seeder)
        $this->assertDatabaseCount('product_prices', 2);
        Product::all()->each(fn (Product $p) => $this->assertNotNull($p->prices()->first()?->price));

        (new ProductPriceSeeder())->run();
        $this->assertDatabaseCount('product_prices', 2);
    }

    // ---- variable products in the price grid --------------------------------

    private function variableWithVariant(string $sku, string $name, ?string $colour = null): array
    {
        $parent = \Modules\Products\Models\Product::factory()->variable()->create(['sku' => $sku]);
        $parent->translations()->create(['locale' => 'it', 'name' => $name]);

        $selection = [];

        if ($colour !== null) {
            $tax = Taxonomy::factory()->named('Colore')->create();
            $term = \Modules\Taxonomies\Models\TaxonomyTerm::factory()->named($colour)->for($tax)->create();
            $selection = [$term->taxonomy_id => [$term->id]];
        }

        $variant = $selection === []
            ? \Modules\Products\Models\Product::factory()->variantOf($parent)->create(['sku' => $sku.'-V'])
            : (new \Modules\Products\Support\VariantGenerator())->generate($parent, $selection)['variants']->first();

        return [$parent, $variant];
    }

    public function test_price_grid_excludes_variable_parents_and_labels_variants(): void
    {
        $list = PriceList::create(['name' => 'Std', 'is_default' => true]);
        [$parent, $variant] = $this->variableWithVariant('TSHIRT', 'Maglietta', 'Rosso');
        $this->product('Semplice', 'PLAIN-1');

        $rows = collect(Livewire::test(ManagePrices::class)->set('priceListId', $list->id)->instance()->rows());

        $this->assertContains('TSHIRT-ROSSO', $rows->pluck('sku')->all());
        $this->assertContains('PLAIN-1', $rows->pluck('sku')->all());
        $this->assertNotContains('TSHIRT', $rows->pluck('sku')->all()); // the variable container is not a row

        $variantRow = $rows->firstWhere('sku', 'TSHIRT-ROSSO');
        $this->assertSame('— Maglietta › Rosso', $variantRow['name']);
    }

    public function test_price_grid_variant_scope_filter(): void
    {
        $list = PriceList::create(['name' => 'Std', 'is_default' => true]);
        [, $variant] = $this->variableWithVariant('AAA', 'Alpha');
        $this->product('Semplice', 'PLAIN-9');

        $page = Livewire::test(ManagePrices::class)->set('priceListId', $list->id)->instance();
        $skus = fn () => collect($page->rows())->pluck('sku')->sort()->values()->all();

        $page->variantScope = 'variants';
        $this->assertSame(['AAA-V'], $skus());

        $page->variantScope = 'simple';
        $this->assertSame(['PLAIN-9'], $skus());

        $page->variantScope = null;
        $this->assertSame(['AAA-V', 'PLAIN-9'], $skus());
    }

    public function test_price_grid_search_matches_the_parent_name(): void
    {
        $list = PriceList::create(['name' => 'Std', 'is_default' => true]);
        [, $variant] = $this->variableWithVariant('MGL', 'Maglietta', 'Blu');
        $this->product('Scarpe', 'SHOE-1');

        $page = Livewire::test(ManagePrices::class)->set('priceListId', $list->id)->instance();
        $page->search = 'Magli';

        $this->assertSame(['MGL-BLU'], collect($page->rows())->pluck('sku')->all());
    }
}
