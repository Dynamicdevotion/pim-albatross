<?php

namespace Modules\WooSync\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Modules\WooSync\Models\WooSyncAttributeLink;
use Modules\WooSync\Models\WooSyncAttributeTermLink;
use Modules\WooSync\Support\AttributeResolver;
use Modules\WooSync\Tests\Support\FakeWooClient;
use Tests\TestCase;

class AttributeResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
    }

    private function taxonomy(string $name): Taxonomy
    {
        $taxonomy = Taxonomy::create(['slug' => Str::slug($name)]);
        $taxonomy->translations()->create(['locale' => 'it', 'name' => $name]);

        return $taxonomy->fresh();
    }

    private function term(Taxonomy $taxonomy, string $name): TaxonomyTerm
    {
        $term = TaxonomyTerm::create(['taxonomy_id' => $taxonomy->id, 'slug' => Str::slug($name)]);
        $term->translations()->create(['locale' => 'it', 'name' => $name]);

        return $term->fresh();
    }

    public function test_variant_taxonomies_is_the_union_across_all_variants_excluding_categorie(): void
    {
        $colore = $this->taxonomy('Colore');
        $rosso = $this->term($colore, 'Rosso');
        $blu = $this->term($colore, 'Blu');

        $categorie = $this->taxonomy('Categorie'); // Str::slug() already yields 'categorie'
        $sedie = $this->term($categorie, 'Sedie');

        $parent = Product::factory()->variable()->create(['sku' => 'VARB-1']);
        $parent->taxonomyTerms()->sync([$sedie->id]); // descriptive, on the parent only — never a variant axis

        $v1 = Product::factory()->variantOf($parent)->create(['sku' => 'VARB-1-V1']);
        $v1->taxonomyTerms()->sync([$rosso->id]);
        $v2 = Product::factory()->variantOf($parent)->create(['sku' => 'VARB-1-V2']);
        $v2->taxonomyTerms()->sync([$blu->id]);

        $taxonomies = AttributeResolver::variantTaxonomies($parent->fresh());

        $this->assertCount(1, $taxonomies);
        $this->assertTrue($taxonomies->has($colore->id));
    }

    public function test_it_creates_a_missing_woo_attribute_and_remembers_the_mapping(): void
    {
        $client = new FakeWooClient;
        $resolver = new AttributeResolver($client);

        $taxonomy = $this->taxonomy('Colore');
        $id = $resolver->attributeIdFor($taxonomy);

        $this->assertContains('createAttribute', $client->calls);
        $this->assertDatabaseHas('woosync_attribute_links', [
            'taxonomy_id' => $taxonomy->id,
            'woocommerce_attribute_id' => $id,
        ]);
    }

    public function test_a_stored_attribute_mapping_is_reused_without_touching_the_api(): void
    {
        $taxonomy = $this->taxonomy('Taglia');
        WooSyncAttributeLink::create(['taxonomy_id' => $taxonomy->id, 'woocommerce_attribute_id' => 777]);

        $client = new FakeWooClient;
        $id = (new AttributeResolver($client))->attributeIdFor($taxonomy);

        $this->assertSame(777, $id);
        $this->assertSame([], $client->calls);
    }

    public function test_it_matches_an_existing_woo_attribute_by_name(): void
    {
        $client = new FakeWooClient;
        $client->attributes = [['id' => 9, 'name' => 'Colore']];

        $id = (new AttributeResolver($client))->attributeIdFor($this->taxonomy('Colore'));

        $this->assertSame(9, $id);
        $this->assertNotContains('createAttribute', $client->calls);
    }

    public function test_it_creates_a_missing_term_inside_the_attribute_and_remembers_the_mapping(): void
    {
        $client = new FakeWooClient;
        $resolver = new AttributeResolver($client);

        $taxonomy = $this->taxonomy('Colore');
        $term = $this->term($taxonomy, 'Rosso');
        $attributeId = $resolver->attributeIdFor($taxonomy);

        $termId = $resolver->termIdFor($term, $attributeId);

        $this->assertContains('createAttributeTerm:'.$attributeId, $client->calls);
        $this->assertDatabaseHas('woosync_attribute_term_links', [
            'taxonomy_term_id' => $term->id,
            'woocommerce_attribute_id' => $attributeId,
            'woocommerce_term_id' => $termId,
        ]);
    }

    public function test_it_matches_an_existing_term_by_name_inside_the_attribute(): void
    {
        $client = new FakeWooClient;
        $client->attributeTerms[5] = [['id' => 42, 'name' => 'Rosso']];

        $taxonomy = $this->taxonomy('Colore');
        $term = $this->term($taxonomy, 'Rosso');

        $id = (new AttributeResolver($client))->termIdFor($term, 5);

        $this->assertSame(42, $id);
        $this->assertNotContains('createAttributeTerm:5', $client->calls);
    }

    public function test_a_stored_term_mapping_is_reused_without_touching_the_api(): void
    {
        $taxonomy = $this->taxonomy('Colore');
        $term = $this->term($taxonomy, 'Rosso');
        WooSyncAttributeTermLink::create([
            'taxonomy_term_id' => $term->id,
            'woocommerce_attribute_id' => 5,
            'woocommerce_term_id' => 555,
        ]);

        $client = new FakeWooClient;
        $id = (new AttributeResolver($client))->termIdFor($term, 5);

        $this->assertSame(555, $id);
        $this->assertSame([], $client->calls);
    }
}
