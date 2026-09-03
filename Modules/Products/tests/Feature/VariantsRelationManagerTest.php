<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Enums\ProductType;
use Modules\Products\Filament\Resources\Products\Pages\EditProduct;
use Modules\Products\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Tests\TestCase;

class VariantsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function variableWithName(string $sku = 'TSHIRT'): Product
    {
        $parent = Product::factory()->variable()->create(['sku' => $sku]);
        $parent->translations()->create(['locale' => 'it', 'name' => 'Maglietta', 'description' => '<p>x</p>']);
        $parent->translations()->create(['locale' => 'en', 'name' => 'T-shirt']);

        return $parent;
    }

    private function manager(Product $owner)
    {
        return Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditProduct::class,
        ]);
    }

    public function test_visible_only_for_variable_products(): void
    {
        $variable = Product::factory()->variable()->create();
        $simple = Product::factory()->create();
        $variant = Product::factory()->variantOf($variable)->create();

        $this->assertTrue(VariantsRelationManager::canViewForRecord($variable, EditProduct::class));
        $this->assertFalse(VariantsRelationManager::canViewForRecord($simple, EditProduct::class));
        $this->assertFalse(VariantsRelationManager::canViewForRecord($variant, EditProduct::class));
    }

    public function test_lists_only_this_parents_variants_and_exposes_its_actions(): void
    {
        $parent = $this->variableWithName('AAA');
        $mine = Product::factory()->variantOf($parent)->create();

        $otherParent = Product::factory()->variable()->create(['sku' => 'BBB']);
        $notMine = Product::factory()->variantOf($otherParent)->create();

        $this->manager($parent)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$notMine])
            ->assertActionExists(TestAction::make('generateVariants')->table())
            ->assertActionExists(TestAction::make('create')->table());
    }

    public function test_create_action_makes_a_variant_and_copies_supplied_translations(): void
    {
        $parent = $this->variableWithName();

        $this->manager($parent)
            ->callAction(TestAction::make('create')->table(), data: [
                'sku' => 'TSHIRT-CUSTOM',
                'stock' => 4,
                'translations' => [
                    'it' => ['name' => 'Maglietta Rossa'],
                    'en' => ['name' => 'Red T-shirt'],
                ],
            ])
            ->assertHasNoErrors();

        $variant = $parent->variants()->sole();
        $this->assertSame('TSHIRT-CUSTOM', $variant->sku);
        $this->assertSame(ProductType::Variant, $variant->type);
        $this->assertSame(4, $variant->stock);
        $this->assertSame('Maglietta Rossa', $variant->translate('it')->name);
        $this->assertSame('Red T-shirt', $variant->translate('en')->name);
    }

    public function test_variant_edit_sets_and_clears_per_list_prices(): void
    {
        $standard = PriceList::create(['name' => 'Standard', 'is_default' => true]);
        $wholesale = PriceList::create(['name' => 'Wholesale']);

        $parent = $this->variableWithName();
        $variant = Product::factory()->variantOf($parent)->create(['sku' => 'TSHIRT-V1']);
        $variant->translations()->create(['locale' => 'it', 'name' => 'V1']);
        $variant->prices()->create(['price_list_id' => $standard->id, 'price' => 30]);

        $this->manager($parent)
            ->callAction(TestAction::make('edit')->table($variant), data: [
                'sku' => 'TSHIRT-V1',
                'stock' => 0,
                'translations' => ['it' => ['name' => 'V1']],
                'prices' => [
                    ['price_list_id' => $standard->id, 'price' => ''],   // clear an existing price
                    ['price_list_id' => $wholesale->id, 'price' => '12'], // add a new one
                ],
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('product_prices', ['product_id' => $variant->id, 'price_list_id' => $standard->id]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $variant->id, 'price_list_id' => $wholesale->id, 'price' => 12.00]);
        $this->assertSame(1, $variant->prices()->count());
    }

    public function test_generate_variants_action_builds_one_variant_per_combination(): void
    {
        $parent = $this->variableWithName();

        $colour = Taxonomy::factory()->named('Colore')->create();
        $rosso = TaxonomyTerm::factory()->named('Rosso')->for($colour)->create();
        $blu = TaxonomyTerm::factory()->named('Blu')->for($colour)->create();

        $size = Taxonomy::factory()->named('Taglia')->create();
        $m = TaxonomyTerm::factory()->named('M')->for($size)->create();

        // The generate step is a modal wizard; its adapter is driven directly.
        $this->manager($parent)->instance()->runGeneration([
            'taxonomies' => [$colour->id, $size->id],
            'terms' => [
                $colour->id => [$rosso->id, $blu->id],
                $size->id => [$m->id],
            ],
            'variants' => [
                ['sku' => 'TSHIRT-ROSSO-M', 'name' => 'Maglietta'],
                ['sku' => 'TSHIRT-BLU-M', 'name' => 'Maglietta'],
            ],
        ]);

        $this->assertEqualsCanonicalizing(
            ['TSHIRT-ROSSO-M', 'TSHIRT-BLU-M'],
            $parent->variants()->pluck('sku')->all(),
        );
        $this->assertSame('Maglietta', $parent->variants()->first()->translate('it')->name);
        $this->assertEqualsCanonicalizing(
            [$rosso->id, $m->id],
            $parent->variants()->where('sku', 'TSHIRT-ROSSO-M')->sole()->taxonomyTerms()->pluck('taxonomy_terms.id')->all(),
        );
    }

    // ---- bulk set stock and dimensions --------------------------------------

    public function test_bulk_set_stock_and_dimensions_only_touches_filled_fields(): void
    {
        $parent = $this->variableWithName();
        $a = Product::factory()->variantOf($parent)->create(['sku' => 'TSHIRT-A', 'stock' => 1, 'weight' => 1, 'length' => 1, 'width' => 1, 'height' => 1]);
        $b = Product::factory()->variantOf($parent)->create(['sku' => 'TSHIRT-B', 'stock' => 2, 'weight' => 2, 'length' => 2, 'width' => 2, 'height' => 2]);

        $this->manager($parent)
            ->callTableBulkAction('bulkSetStockAndDimensions', [$a, $b], [
                'stock' => '10',
                'weight' => '2.5',
                // length / width / height left blank -> untouched
            ])
            ->assertNotified(trans_choice('pim.notification.stock_dimensions_bulk_updated', 2, ['count' => 2]));

        foreach ([$a, $b] as $variant) {
            $fresh = $variant->fresh();
            $this->assertSame(10, $fresh->stock);
            $this->assertSame('2.500', (string) $fresh->weight);
        }
        $this->assertSame('1.00', (string) $a->fresh()->length);
        $this->assertSame('2.00', (string) $b->fresh()->length);
    }

    public function test_bulk_set_stock_and_dimensions_never_excludes_anything_here(): void
    {
        // Unlike the equivalent bulk action on the main products list, every
        // row in this table is already a `variant` — there is no `variable`
        // container to filter out, so a plain "N updated" notification is
        // the whole story.
        $parent = $this->variableWithName();
        $variant = Product::factory()->variantOf($parent)->create(['sku' => 'TSHIRT-C', 'stock' => 0]);

        $this->manager($parent)
            ->callTableBulkAction('bulkSetStockAndDimensions', [$variant], ['stock' => '7'])
            ->assertNotified(trans_choice('pim.notification.stock_dimensions_bulk_updated', 1, ['count' => 1]));

        $this->assertSame(7, $variant->fresh()->stock);
    }

    public function test_bulk_set_stock_and_dimensions_with_nothing_filled_warns_and_changes_nothing(): void
    {
        $parent = $this->variableWithName();
        $variant = Product::factory()->variantOf($parent)->create(['sku' => 'TSHIRT-D', 'stock' => 3]);

        $this->manager($parent)
            ->callTableBulkAction('bulkSetStockAndDimensions', [$variant], [])
            ->assertNotified(__('pim.notification.bulk_dimensions_nothing_to_set'));

        $this->assertSame(3, $variant->fresh()->stock);
    }
}
