<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ImportGestionali\Support\TaxonomyResolution;
use Modules\ImportGestionali\Support\TaxonomyTermResolver;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Tests\TestCase;

class TaxonomyTermResolverTest extends TestCase
{
    use RefreshDatabase;

    private Taxonomy $colore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);

        $this->colore = Taxonomy::create(['slug' => 'colore']);
        $this->colore->translations()->create(['locale' => 'it', 'name' => 'Colore']);
    }

    private function term(string $name, string $slug): TaxonomyTerm
    {
        $term = $this->colore->terms()->create(['slug' => $slug]);
        $term->translations()->create(['locale' => 'it', 'name' => $name]);

        return $term;
    }

    public function test_matches_an_existing_term_by_name_case_insensitively(): void
    {
        $rosso = $this->term('Rosso', 'rosso');

        $resolution = (new TaxonomyTermResolver)->resolve($this->colore->id, ['  rOsSo '], dryRun: false);

        $this->assertSame([$rosso->id], $resolution->resolvedIds());
        $this->assertSame(TaxonomyResolution::FOUND, $resolution->terms[0]['status']);
        $this->assertSame([], $resolution->missingNames());
    }

    public function test_an_unknown_term_is_missing_when_creation_is_off(): void
    {
        $resolution = (new TaxonomyTermResolver(createMissingTerms: false))
            ->resolve($this->colore->id, ['Verde'], dryRun: false);

        $this->assertSame([], $resolution->resolvedIds());
        $this->assertSame(['Verde'], $resolution->missingNames());
        $this->assertSame(0, TaxonomyTerm::count());
    }

    public function test_dry_run_with_creation_on_reports_will_create_without_writing(): void
    {
        $resolution = (new TaxonomyTermResolver(createMissingTerms: true))
            ->resolve($this->colore->id, ['Verde'], dryRun: true);

        $this->assertSame(TaxonomyResolution::WILL_CREATE, $resolution->terms[0]['status']);
        $this->assertSame(0, TaxonomyTerm::count());
    }

    public function test_creation_on_makes_a_root_term_with_a_base_translation(): void
    {
        $resolution = (new TaxonomyTermResolver(createMissingTerms: true))
            ->resolve($this->colore->id, ['Verde acqua'], dryRun: false);

        $term = TaxonomyTerm::sole();
        $this->assertSame($this->colore->id, $term->taxonomy_id);
        $this->assertNull($term->parent_id);
        $this->assertSame('verde-acqua', $term->slug);
        $this->assertSame('Verde acqua', $term->name);
        $this->assertSame([$term->id], $resolution->resolvedIds());
        $this->assertSame(TaxonomyResolution::CREATED, $resolution->terms[0]['status']);
    }

    public function test_a_term_created_for_one_row_is_reused_by_the_next(): void
    {
        $resolver = new TaxonomyTermResolver(createMissingTerms: true);

        $first = $resolver->resolve($this->colore->id, ['Verde'], dryRun: false);
        $second = $resolver->resolve($this->colore->id, ['verde'], dryRun: false);

        $this->assertSame(1, TaxonomyTerm::count());
        $this->assertSame($first->resolvedIds(), $second->resolvedIds());
        $this->assertSame(TaxonomyResolution::FOUND, $second->terms[0]['status']);
    }

    public function test_several_new_names_in_one_call_each_get_their_own_term(): void
    {
        $resolution = (new TaxonomyTermResolver(createMissingTerms: true))
            ->resolve($this->colore->id, ['Verde', 'Giallo', 'Verde'], dryRun: false);

        // "Verde" twice -> created once, reused; "Giallo" -> its own term
        $this->assertSame(2, TaxonomyTerm::count());
        $this->assertCount(2, array_unique($resolution->resolvedIds()));
        $this->assertEqualsCanonicalizing(['verde', 'giallo'], $this->colore->terms()->pluck('slug')->all());
    }

    public function test_a_missing_taxonomy_is_reported_as_gone(): void
    {
        $resolution = (new TaxonomyTermResolver)->resolve(99999, ['Rosso'], dryRun: false);

        $this->assertTrue($resolution->gone);
        $this->assertSame([], $resolution->resolvedIds());
    }
}
