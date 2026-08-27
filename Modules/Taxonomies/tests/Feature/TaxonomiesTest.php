<?php

namespace Modules\Taxonomies\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Filament\Resources\Taxonomies\Pages\CreateTaxonomy;
use Modules\Taxonomies\Filament\Resources\Taxonomies\Pages\EditTaxonomy;
use Modules\Taxonomies\Filament\Resources\Taxonomies\RelationManagers\TermsRelationManager;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Tests\TestCase;

class TaxonomiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_slug_is_generated_and_kept_unique(): void
    {
        $a = Taxonomy::create(['name' => 'Categoria']);
        $b = Taxonomy::create(['name' => 'Categoria']);

        $this->assertSame('categoria', $a->slug);
        $this->assertSame('categoria-2', $b->slug);

        // term slugs are unique per taxonomy, not globally
        $t1 = $a->terms()->create(['name' => 'Rosso']);
        $t2 = $b->terms()->create(['name' => 'Rosso']);
        $this->assertSame('rosso', $t1->slug);
        $this->assertSame('rosso', $t2->slug);
    }

    public function test_term_hierarchy_and_descendant_ids(): void
    {
        $cat = Taxonomy::create(['name' => 'Categoria']);
        $clothing = $cat->terms()->create(['name' => 'Abbigliamento']);
        $shoes = $cat->terms()->create(['name' => 'Scarpe', 'parent_id' => $clothing->id]);
        $sneakers = $cat->terms()->create(['name' => 'Sneaker', 'parent_id' => $shoes->id]);

        $this->assertTrue($shoes->parent->is($clothing));
        $this->assertEqualsCanonicalizing(['Scarpe'], $clothing->children->pluck('name')->all());
        $this->assertSame('Categoria', $sneakers->taxonomy->name);
        $this->assertEqualsCanonicalizing(
            [$shoes->id, $sneakers->id],
            $clothing->descendantIds(),
        );
    }

    public function test_products_hold_terms_from_multiple_taxonomies_and_cascades(): void
    {
        $cat = Taxonomy::create(['name' => 'Categoria']);
        $col = Taxonomy::create(['name' => 'Colore']);
        $clothing = $cat->terms()->create(['name' => 'Abbigliamento']);
        $red = $col->terms()->create(['name' => 'Rosso']);

        $product = Product::create(['sku' => 'TX-1', 'status' => 'draft']);
        $product->taxonomyTerms()->sync([$clothing->id, $red->id]);

        $this->assertEqualsCanonicalizing(
            ['Abbigliamento', 'Rosso'],
            $product->taxonomyTerms->pluck('name')->all(),
        );
        $this->assertTrue($red->products->contains($product));

        // deleting the taxonomy removes its terms and the pivot rows
        $col->delete();
        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $red->id]);
        $this->assertDatabaseMissing('product_taxonomy_term', ['taxonomy_term_id' => $red->id]);

        // deleting a parent term orphans its children (nullOnDelete)
        $child = $cat->terms()->create(['name' => 'Scarpe', 'parent_id' => $clothing->id]);
        $clothing->delete();
        $this->assertNull($child->fresh()->parent_id);

        // deleting the product removes its pivot rows
        $product->delete();
        $this->assertDatabaseCount('product_taxonomy_term', 0);
    }

    public function test_create_taxonomy_page_persists_with_generated_slug(): void
    {
        Livewire::test(CreateTaxonomy::class)
            ->fillForm(['name' => 'Materiale'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('taxonomies', ['name' => 'Materiale', 'slug' => 'materiale']);
    }

    public function test_terms_relation_manager_lists_and_accepts_a_child_term(): void
    {
        $taxonomy = Taxonomy::create(['name' => 'Categoria']);
        $parent = $taxonomy->terms()->create(['name' => 'Abbigliamento']);

        Livewire::test(TermsRelationManager::class, [
            'ownerRecord' => $taxonomy,
            'pageClass' => EditTaxonomy::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$parent]);

        // a child added through the relationship keeps taxonomy_id and parent_id
        $child = $taxonomy->terms()->create(['name' => 'Scarpe', 'parent_id' => $parent->id]);
        $this->assertSame($taxonomy->id, $child->taxonomy_id);
        $this->assertSame($parent->id, $child->parent_id);
    }

    public function test_product_form_attaches_terms_from_different_taxonomies(): void
    {
        $clothing = Taxonomy::create(['name' => 'Categoria'])->terms()->create(['name' => 'Abbigliamento']);
        $red = Taxonomy::create(['name' => 'Colore'])->terms()->create(['name' => 'Rosso']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku' => 'TX-FORM',
                'status' => 'draft',
                'taxonomyTerms' => [$clothing->id, $red->id],
                'translations' => ['it' => ['name' => 'Maglietta']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'TX-FORM')->sole();

        $this->assertEqualsCanonicalizing(
            [$clothing->id, $red->id],
            $product->taxonomyTerms->pluck('id')->all(),
        );
        $this->assertDatabaseCount('product_taxonomy_term', 2);
    }
}
