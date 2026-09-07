<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ImportGestionali\Support\MappingTarget;
use Modules\ImportGestionali\Support\ProductRowImporter;
use Modules\ImportGestionali\Support\RowOutcome;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Tests\TestCase;

/**
 * The multi-language column mapping (`translation:{languageId}:{field}`, see
 * {@see MappingTarget}) and what {@see ProductRowImporter} does with it —
 * complementary to {@see ProductRowImporterTest}, which covers the legacy
 * bare `name`/`description` alias and everything not translation-specific.
 */
class ProductRowImporterTranslationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
    }

    private function import(array $row, int $line = 2, bool $updateExisting = false): RowOutcome
    {
        $seen = [];

        return ProductRowImporter::make()->import($row, $line, $updateExisting, $seen);
    }

    private function english(): Language
    {
        return Language::query()->where('code', 'en')->sole();
    }

    public function test_writes_translations_for_several_active_languages_in_one_row(): void
    {
        $base = Locales::base();
        $en = $this->english();

        $this->import([
            'sku' => 'T1',
            MappingTarget::forTranslation($base->id, 'name') => 'Sedia',
            MappingTarget::forTranslation($base->id, 'description') => 'Una sedia comoda',
            MappingTarget::forTranslation($en->id, 'name') => 'Chair',
            MappingTarget::forTranslation($en->id, 'description') => 'A comfortable chair',
        ]);

        $product = Product::where('sku', 'T1')->sole();

        $this->assertSame('Sedia', $product->translate('it')->name);
        $this->assertSame('Una sedia comoda', $product->translate('it')->description);
        $this->assertSame('Chair', $product->translate('en')->name);
        $this->assertSame('A comfortable chair', $product->translate('en')->description);
    }

    public function test_slug_is_generated_from_the_name_when_left_blank(): void
    {
        $base = Locales::base();

        $this->import([
            'sku' => 'T2',
            MappingTarget::forTranslation($base->id, 'name') => 'Anello Solitario',
        ]);

        $this->assertSame('anello-solitario', Product::where('sku', 'T2')->sole()->translate('it')->slug);
    }

    public function test_slug_is_deduplicated_within_the_same_language(): void
    {
        $base = Locales::base();

        $this->import(['sku' => 'T3', MappingTarget::forTranslation($base->id, 'name') => 'Anello'], 2);
        $this->import(['sku' => 'T4', MappingTarget::forTranslation($base->id, 'name') => 'Anello'], 3);

        $this->assertSame('anello', Product::where('sku', 'T3')->sole()->translate('it')->slug);
        $this->assertSame('anello-2', Product::where('sku', 'T4')->sole()->translate('it')->slug);
    }

    public function test_a_submitted_slug_is_sanitized_and_used(): void
    {
        $base = Locales::base();

        $this->import([
            'sku' => 'T5',
            MappingTarget::forTranslation($base->id, 'name') => 'Anello',
            MappingTarget::forTranslation($base->id, 'slug') => 'Anello Custom!!',
        ]);

        $this->assertSame('anello-custom', Product::where('sku', 'T5')->sole()->translate('it')->slug);
    }

    public function test_update_leaves_untouched_fields_alone_including_the_slug(): void
    {
        $base = Locales::base();
        $product = Product::factory()->create(['sku' => 'T6']);
        $product->translations()->create([
            'language_id' => $base->id,
            'name' => 'Vecchio',
            'slug' => 'vecchio-slug',
            'description' => 'Descrizione vecchia',
            'meta_title' => 'Meta vecchio',
        ]);

        $this->import([
            'sku' => 'T6',
            MappingTarget::forTranslation($base->id, 'description') => 'Descrizione nuova',
        ], 2, updateExisting: true);

        $translation = $product->fresh()->translate('it');
        $this->assertSame('Vecchio', $translation->name);
        $this->assertSame('vecchio-slug', $translation->slug);
        $this->assertSame('Descrizione nuova', $translation->description);
        $this->assertSame('Meta vecchio', $translation->meta_title);
    }

    public function test_a_non_base_language_without_a_name_is_not_created(): void
    {
        $base = Locales::base();
        $en = $this->english();

        $this->import([
            'sku' => 'T7',
            MappingTarget::forTranslation($base->id, 'name') => 'Sedia',
            MappingTarget::forTranslation($en->id, 'meta_title') => 'Chair meta title',
        ]);

        $product = Product::where('sku', 'T7')->sole();

        $this->assertNotNull($product->translate('it'));
        $this->assertNull($product->translate('en'));
    }

    public function test_a_non_base_language_with_a_name_is_created_and_needs_its_own_slug(): void
    {
        $base = Locales::base();
        $en = $this->english();

        $this->import([
            'sku' => 'T8',
            MappingTarget::forTranslation($base->id, 'name') => 'Sedia',
            MappingTarget::forTranslation($en->id, 'name') => 'Chair',
        ]);

        $translation = Product::where('sku', 'T8')->sole()->translate('en');
        $this->assertSame('Chair', $translation->name);
        $this->assertSame('chair', $translation->slug);
    }

    public function test_meta_title_and_meta_description_are_written(): void
    {
        $base = Locales::base();

        $this->import([
            'sku' => 'T9',
            MappingTarget::forTranslation($base->id, 'name') => 'Sedia',
            MappingTarget::forTranslation($base->id, 'meta_title') => 'Sedia in vendita',
            MappingTarget::forTranslation($base->id, 'meta_description') => 'La migliore sedia',
        ]);

        $translation = Product::where('sku', 'T9')->sole()->translate('it');
        $this->assertSame('Sedia in vendita', $translation->meta_title);
        $this->assertSame('La migliore sedia', $translation->meta_description);
    }

    public function test_the_legacy_bare_name_key_is_equivalent_to_the_base_language_translation_target(): void
    {
        $base = Locales::base();

        $this->import(['sku' => 'T10', 'name' => 'Sedia Legacy'], 2);
        $this->import(['sku' => 'T11', MappingTarget::forTranslation($base->id, 'name') => 'Sedia Legacy'], 3);

        $this->assertSame('Sedia Legacy', Product::where('sku', 'T10')->sole()->translate('it')->name);
        $this->assertSame('Sedia Legacy', Product::where('sku', 'T11')->sole()->translate('it')->name);
        // Same dedup sequence proves both went through the exact same write path.
        $this->assertSame('sedia-legacy', Product::where('sku', 'T10')->sole()->translate('it')->slug);
        $this->assertSame('sedia-legacy-2', Product::where('sku', 'T11')->sole()->translate('it')->slug);
    }

    public function test_translation_columns_collapse_to_the_base_language_when_multilanguage_is_disabled(): void
    {
        $base = Locales::base();
        $en = $this->english();
        config(['localization.multilanguage_enabled' => false]);

        $this->import([
            'sku' => 'T12',
            MappingTarget::forTranslation($base->id, 'name') => 'Sedia',
            MappingTarget::forTranslation($en->id, 'name') => 'Chair',
        ]);

        $product = Product::where('sku', 'T12')->sole();
        $this->assertSame('Sedia', $product->translate('it')->name);
        // The English cell was still mapped and filled — the flag doesn't
        // erase data the file provides, it only limits Locales::active().
        $this->assertSame('Chair', $product->translate('en')->name);
    }
}
