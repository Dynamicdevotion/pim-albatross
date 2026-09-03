<?php

namespace Modules\Taxonomies\Tests\Feature;

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Taxonomies\Filament\Resources\Taxonomies\Pages\CreateTaxonomy;
use Modules\Taxonomies\Filament\Resources\Taxonomies\Pages\EditTaxonomy;
use Modules\Taxonomies\Filament\Resources\Taxonomies\RelationManagers\TermsRelationManager;
use Modules\Taxonomies\Models\Taxonomy;
use Tests\TestCase;

/**
 * The new per-language, user-facing slug (+ rich-text description) added to
 * taxonomy_translations / taxonomy_term_translations — distinct from the
 * pre-existing `slug` column on taxonomies/taxonomy_terms, which stays the
 * stable internal identifier WooSync/ImportGestionali look up by, untouched
 * (see the class docblock on HandlesTranslatableName).
 */
class TaxonomyTranslatedSlugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_internal_code_still_generates_from_the_base_name_untouched(): void
    {
        Livewire::test(CreateTaxonomy::class)
            ->fillForm(['translations' => ['it' => ['name' => 'Materiale']]])
            ->call('create')
            ->assertHasNoFormErrors();

        // unchanged pre-existing behaviour: the parent record's own slug
        $taxonomy = Taxonomy::query()->where('slug', 'materiale')->sole();
        $this->assertSame('Materiale', $taxonomy->name);
    }

    public function test_a_blank_translated_slug_is_generated_from_this_languages_name(): void
    {
        Livewire::test(CreateTaxonomy::class)
            ->fillForm(['translations' => [
                'it' => ['name' => 'Colore Primario'],
                'en' => ['name' => 'Primary Colour'],
            ]])
            ->call('create')
            ->assertHasNoFormErrors();

        $taxonomy = Taxonomy::query()->where('slug', 'colore-primario')->sole();
        $this->assertSame('colore-primario', $taxonomy->translate('it')->slug);
        $this->assertSame('primary-colour', $taxonomy->translate('en')->slug);
    }

    public function test_description_is_stored_per_language_on_the_taxonomy(): void
    {
        Livewire::test(CreateTaxonomy::class)
            ->fillForm(['translations' => [
                'it' => ['name' => 'Colore', 'description' => '<p>Descrive il colore del prodotto.</p>'],
            ]])
            ->call('create')
            ->assertHasNoFormErrors();

        $taxonomy = Taxonomy::query()->where('slug', 'colore')->sole();
        $this->assertSame('<p>Descrive il colore del prodotto.</p>', $taxonomy->translate('it')->description);
    }

    public function test_two_taxonomies_cannot_share_a_translated_slug_in_the_same_language(): void
    {
        Livewire::test(CreateTaxonomy::class)
            ->fillForm(['translations' => ['it' => ['name' => 'Uno', 'slug' => 'stessa-cosa']]])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateTaxonomy::class)
            ->fillForm(['translations' => ['it' => ['name' => 'Due', 'slug' => 'stessa-cosa']]])
            ->call('create')
            ->assertHasFormErrors(['translations.it.slug']);
    }

    public function test_terms_scope_translated_slug_uniqueness_to_their_own_taxonomy(): void
    {
        $colore = Taxonomy::factory()->named('Colore')->create();
        $taglia = Taxonomy::factory()->named('Taglia')->create();

        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $colore, 'pageClass' => EditTaxonomy::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'slug' => '',
                'translations' => ['it' => ['name' => 'Rosso', 'slug' => 'condiviso']],
            ])
            ->assertHasNoErrors();

        // same translated slug, but a DIFFERENT taxonomy -> allowed
        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $taglia, 'pageClass' => EditTaxonomy::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'slug' => '',
                'translations' => ['it' => ['name' => 'Rosso Scuro', 'slug' => 'condiviso']],
            ])
            ->assertHasNoErrors();

        // same translated slug, SAME taxonomy -> rejected
        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $colore, 'pageClass' => EditTaxonomy::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'slug' => '',
                'translations' => ['it' => ['name' => 'Rosso Bis', 'slug' => 'condiviso']],
            ])
            ->assertHasErrors(['mountedActions.0.data.translations.it.slug']);
    }

    public function test_editing_a_term_can_keep_its_slug_and_description(): void
    {
        $taxonomy = Taxonomy::factory()->named('Categoria')->create();
        $term = $taxonomy->terms()->create(['slug' => 'abbigliamento']);
        $term->translations()->create([
            'locale' => 'it',
            'name' => 'Abbigliamento',
            'slug' => 'abbigliamento',
            'description' => '<p>Vestiti e accessori.</p>',
        ]);

        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $taxonomy, 'pageClass' => EditTaxonomy::class])
            ->callAction(TestAction::make('edit')->table($term), data: [
                'slug' => $term->slug,
                'translations' => [
                    'it' => [
                        'name' => 'Abbigliamento e Accessori',
                        'slug' => 'abbigliamento',
                        'description' => '<p>Vestiti e accessori.</p>',
                    ],
                ],
            ])
            ->assertHasNoErrors();

        $fresh = $term->fresh();
        $this->assertSame('Abbigliamento e Accessori', $fresh->name);
        $this->assertSame('abbigliamento', $fresh->translate('it')->slug);
        $this->assertSame('<p>Vestiti e accessori.</p>', $fresh->translate('it')->description);
    }
}
