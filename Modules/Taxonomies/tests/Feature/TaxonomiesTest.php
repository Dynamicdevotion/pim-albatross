<?php

namespace Modules\Taxonomies\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Models\Language;
use Modules\Products\Filament\Resources\Products\Pages\CreateProduct;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Database\Seeders\TaxonomySeeder;
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

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** Create a term with a base-language name. */
    private function term(Taxonomy $taxonomy, string $name, ?TaxonomyTerm $parent = null): TaxonomyTerm
    {
        $term = $taxonomy->terms()->create([
            'slug' => \Illuminate\Support\Str::slug($name),
            'parent_id' => $parent?->getKey(),
        ]);
        $term->translations()->create(['locale' => 'it', 'name' => $name]);

        return $term->refresh();
    }

    public function test_taxonomy_name_is_the_base_language_translation(): void
    {
        $taxonomy = Taxonomy::create(['slug' => 'categoria']);
        $taxonomy->translations()->create(['locale' => 'it', 'name' => 'Categoria']);
        $taxonomy->translations()->create(['locale' => 'en', 'name' => 'Category']);

        $this->assertSame('Categoria', $taxonomy->fresh()->name);
        $this->assertSame('Category', $taxonomy->translate('en')->name);
        $this->assertNull($taxonomy->translate('de'));
    }

    public function test_term_hierarchy_and_descendant_ids(): void
    {
        $cat = Taxonomy::factory()->named('Categoria')->create();
        $clothing = $this->term($cat, 'Abbigliamento');
        $shoes = $this->term($cat, 'Scarpe', $clothing);
        $sneakers = $this->term($cat, 'Sneaker', $shoes);

        $this->assertTrue($shoes->parent->is($clothing));
        $this->assertSame('Categoria', $sneakers->taxonomy->name);
        $this->assertEqualsCanonicalizing([$shoes->id, $sneakers->id], $clothing->descendantIds());
    }

    public function test_products_hold_terms_from_multiple_taxonomies_and_cascades(): void
    {
        $clothing = $this->term(Taxonomy::factory()->named('Categoria')->create(), 'Abbigliamento');
        $red = $this->term(Taxonomy::factory()->named('Colore')->create(), 'Rosso');

        $product = Product::create(['sku' => 'TX-1', 'status' => 'draft']);
        $product->taxonomyTerms()->sync([$clothing->id, $red->id]);

        $this->assertEqualsCanonicalizing(
            ['Abbigliamento', 'Rosso'],
            $product->taxonomyTerms->map->name->all(),
        );

        $red->taxonomy->delete();
        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $red->id]);
        $this->assertDatabaseMissing('taxonomy_term_translations', ['taxonomy_term_id' => $red->id]);
        $this->assertDatabaseMissing('product_taxonomy_term', ['taxonomy_term_id' => $red->id]);

        $child = $this->term($clothing->taxonomy, 'Scarpe', $clothing);
        $clothing->delete();
        $this->assertNull($child->fresh()->parent_id);

        $product->delete();
        $this->assertDatabaseCount('product_taxonomy_term', 0);
    }

    public function test_create_taxonomy_page_saves_translations_and_generates_the_slug(): void
    {
        Livewire::test(CreateTaxonomy::class)
            ->fillForm([
                'slug' => '',
                'translations' => [
                    'it' => ['name' => 'Materiale'],
                    'en' => ['name' => 'Material'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $taxonomy = Taxonomy::query()->where('slug', 'materiale')->sole();
        $this->assertSame('Materiale', $taxonomy->name);
        $this->assertSame('Material', $taxonomy->translate('en')->name);
    }

    public function test_edit_taxonomy_page_prefills_and_updates_translations(): void
    {
        $taxonomy = Taxonomy::factory()->named('Categoria')->create();

        $component = Livewire::test(EditTaxonomy::class, ['record' => $taxonomy->getRouteKey()]);
        $this->assertSame('Categoria', $component->get('data')['translations']['it']['name']);

        $component
            ->fillForm(['translations' => [
                'it' => ['name' => 'Categorie'],
                'en' => ['name' => 'Categories'],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Categorie', $taxonomy->fresh()->name);
        $this->assertSame('Categories', $taxonomy->fresh()->translate('en')->name);
    }

    public function test_terms_relation_manager_lists_and_accepts_a_child_term(): void
    {
        $taxonomy = Taxonomy::factory()->named('Categoria')->create();
        $parent = $this->term($taxonomy, 'Abbigliamento');

        Livewire::test(TermsRelationManager::class, [
            'ownerRecord' => $taxonomy,
            'pageClass' => EditTaxonomy::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$parent]);

        $child = $this->term($taxonomy, 'Scarpe', $parent);
        $this->assertSame($taxonomy->id, $child->taxonomy_id);
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame('Scarpe', $child->name);
    }

    public function test_product_form_attaches_terms_from_different_taxonomies(): void
    {
        $clothing = $this->term(Taxonomy::factory()->named('Categoria')->create(), 'Abbigliamento');
        $red = $this->term(Taxonomy::factory()->named('Colore')->create(), 'Rosso');

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
        $this->assertEqualsCanonicalizing([$clothing->id, $red->id], $product->taxonomyTerms->pluck('id')->all());
    }

    public function test_example_seeder_is_hierarchical_and_idempotent(): void
    {
        (new TaxonomySeeder())->run();

        $this->assertEqualsCanonicalizing(
            ['categoria', 'colore', 'taglia', 'materiale'],
            Taxonomy::pluck('slug')->all(),
        );

        $categoria = Taxonomy::query()->where('slug', 'categoria')->sole();
        $this->assertSame('Categoria', $categoria->name);

        $abbigliamento = $categoria->terms()->where('slug', 'abbigliamento')->sole();
        $this->assertNull($abbigliamento->parent_id);
        $this->assertEqualsCanonicalizing(
            ['Magliette', 'Pantaloni', 'Giacche'],
            $abbigliamento->children->map->name->all(),
        );

        $before = TaxonomyTerm::count();
        (new TaxonomySeeder())->run();
        $this->assertSame($before, TaxonomyTerm::count());
        $this->assertCount(4, Taxonomy::all());
    }

    public function test_products_list_bulk_assigns_taxonomy_terms(): void
    {
        $taxonomy = Taxonomy::factory()->named('Categoria')->create();
        $a = $this->term($taxonomy, 'Abbigliamento');
        $b = $this->term($taxonomy, 'Scarpe');

        $p1 = Product::create(['sku' => 'BULK-1', 'status' => 'draft']);
        $p2 = Product::create(['sku' => 'BULK-2', 'status' => 'draft']);
        $p2->taxonomyTerms()->attach($a->id); // already has one

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('assignTaxonomyTerms', [$p1, $p2], ['terms' => [$a->id, $b->id]]);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $p1->fresh()->taxonomyTerms->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $p2->fresh()->taxonomyTerms->pluck('id')->all());
        $this->assertDatabaseCount('product_taxonomy_term', 4); // no duplicate for p2/$a
    }
}
