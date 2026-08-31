<?php

namespace Modules\WooSync\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Modules\WooSync\Models\WooSyncCategoryLink;
use Modules\WooSync\Support\CategoryResolver;
use Modules\WooSync\Tests\Support\FakeWooClient;
use Tests\TestCase;

class CategoryResolverTest extends TestCase
{
    use RefreshDatabase;

    private Taxonomy $taxonomy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->taxonomy = Taxonomy::create(['slug' => 'categorie']);
    }

    private function term(string $name, ?TaxonomyTerm $parent = null): TaxonomyTerm
    {
        $term = TaxonomyTerm::create([
            'taxonomy_id' => $this->taxonomy->id,
            'parent_id' => $parent?->id,
            'slug' => Str::slug($name),
        ]);
        $term->translations()->create(['locale' => 'it', 'name' => $name]);

        return $term->fresh();
    }

    public function test_it_creates_a_missing_woo_category_and_remembers_the_mapping(): void
    {
        $client = new FakeWooClient;
        $resolver = new CategoryResolver($client);

        $term = $this->term('Sedie');
        $ids = $resolver->idsFor($term);

        $this->assertCount(1, $ids);
        $this->assertContains('createCategory', $client->calls);
        $this->assertDatabaseHas('woosync_category_links', [
            'taxonomy_term_id' => $term->id,
            'woocommerce_category_id' => $ids[0],
        ]);
    }

    public function test_a_stored_mapping_is_reused_without_touching_the_api(): void
    {
        $term = $this->term('Tavoli');
        WooSyncCategoryLink::create(['taxonomy_term_id' => $term->id, 'woocommerce_category_id' => 555]);

        $client = new FakeWooClient;
        $ids = (new CategoryResolver($client))->idsFor($term);

        $this->assertSame([555], $ids);
        $this->assertSame([], $client->calls);
    }

    public function test_it_matches_an_existing_woo_category_by_name(): void
    {
        $client = new FakeWooClient;
        $client->categories = [['id' => 7, 'name' => 'Sedie', 'parent' => 0]];

        $ids = (new CategoryResolver($client))->idsFor($this->term('Sedie'));

        $this->assertSame([7], $ids);
        $this->assertNotContains('createCategory', $client->calls);
    }

    public function test_a_child_term_creates_its_parent_first(): void
    {
        $client = new FakeWooClient;
        $parent = $this->term('Arredamento');
        $child = $this->term('Sedie', $parent);

        (new CategoryResolver($client))->idsFor($child);

        $this->assertCount(2, $client->createdCategories);
        $this->assertSame('Arredamento', $client->createdCategories[0]['name']);
        $this->assertSame('Sedie', $client->createdCategories[1]['name']);
        $this->assertSame($client->createdCategories[0]['id'], $client->createdCategories[1]['parent']);
    }
}
