<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ImportGestionali\Support\MappingTarget;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Models\Taxonomy;
use Tests\TestCase;

class MappingTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_recognises_the_taxonomy_convention(): void
    {
        $this->assertTrue(MappingTarget::isTaxonomy('taxonomy:5'));
        $this->assertTrue(MappingTarget::isTaxonomy('taxonomy:123'));
        $this->assertFalse(MappingTarget::isTaxonomy('sku'));
        $this->assertFalse(MappingTarget::isTaxonomy('taxonomy:0'));
        $this->assertFalse(MappingTarget::isTaxonomy('taxonomy:x'));
        $this->assertFalse(MappingTarget::isTaxonomy(''));
        $this->assertFalse(MappingTarget::isTaxonomy(null));
    }

    public function test_builds_and_parses_a_taxonomy_target(): void
    {
        $this->assertSame('taxonomy:7', MappingTarget::forTaxonomy(7));
        $this->assertSame(7, MappingTarget::taxonomyId('taxonomy:7'));
    }

    public function test_recognises_the_translation_convention(): void
    {
        $this->assertTrue(MappingTarget::isTranslation('translation:5:name'));
        $this->assertTrue(MappingTarget::isTranslation('translation:123:meta_description'));
        $this->assertFalse(MappingTarget::isTranslation('sku'));
        $this->assertFalse(MappingTarget::isTranslation('taxonomy:5'));
        $this->assertFalse(MappingTarget::isTranslation('translation:0:name'));
        $this->assertFalse(MappingTarget::isTranslation('translation:5'));
        $this->assertFalse(MappingTarget::isTranslation(''));
        $this->assertFalse(MappingTarget::isTranslation(null));
    }

    public function test_builds_and_parses_a_translation_target(): void
    {
        $target = MappingTarget::forTranslation(9, 'meta_title');

        $this->assertSame('translation:9:meta_title', $target);
        $this->assertSame(9, MappingTarget::translationLanguageId($target));
        $this->assertSame('meta_title', MappingTarget::translationField($target));
    }

    public function test_label_resolves_fixed_fields_taxonomies_and_translations(): void
    {
        $this->seed(LanguageSeeder::class);
        $base = Locales::base();

        $this->assertSame(__('pim.import.field.sku'), MappingTarget::label('sku'));
        $this->assertSame('Colore', MappingTarget::label('taxonomy:5', [5 => 'Colore']));
        $this->assertSame('taxonomy:9', MappingTarget::label('taxonomy:9', []));
        $this->assertSame(
            __('pim.import.field.name').' ('.$base->name.')',
            MappingTarget::label(MappingTarget::forTranslation($base->id, 'name')),
        );
    }

    public function test_select_options_group_the_fixed_fields_and_every_taxonomy(): void
    {
        $this->seed(LanguageSeeder::class);

        $colore = Taxonomy::create(['slug' => 'colore']);
        $colore->translations()->create(['locale' => 'it', 'name' => 'Colore']);
        $materiale = Taxonomy::create(['slug' => 'materiale']);
        $materiale->translations()->create(['locale' => 'it', 'name' => 'Materiale']);

        $options = MappingTarget::selectOptions();

        $this->assertSame('', array_key_first($options));
        $this->assertArrayHasKey('sku', $options[__('pim.import.group.fields')]);

        $taxonomyGroup = $options[__('pim.import.group.taxonomies')];
        $this->assertSame('Colore', $taxonomyGroup['taxonomy:'.$colore->id]);
        $this->assertSame('Materiale', $taxonomyGroup['taxonomy:'.$materiale->id]);
    }

    public function test_select_options_omit_the_taxonomy_group_when_there_are_none(): void
    {
        $this->seed(LanguageSeeder::class);

        $this->assertArrayNotHasKey(__('pim.import.group.taxonomies'), MappingTarget::selectOptions());
    }

    public function test_select_options_no_longer_offer_the_fixed_name_and_description_fields(): void
    {
        $this->seed(LanguageSeeder::class);

        $fields = MappingTarget::selectOptions()[__('pim.import.group.fields')];

        $this->assertArrayNotHasKey('name', $fields);
        $this->assertArrayNotHasKey('description', $fields);
        // Untouched: not translatable, still fixed fields.
        $this->assertArrayHasKey('sku', $fields);
        $this->assertArrayHasKey('price', $fields);
    }

    public function test_select_options_include_one_translation_entry_per_active_language_and_field(): void
    {
        $this->seed(LanguageSeeder::class);

        $translations = MappingTarget::selectOptions()[__('pim.import.group.translations')];
        $base = Locales::base();

        $this->assertSame(
            __('pim.import.field.name').' ('.$base->name.')',
            $translations[MappingTarget::forTranslation($base->id, 'name')],
        );
        $this->assertCount(Locales::active()->count() * 5, $translations);
    }

    public function test_select_options_translations_collapse_to_the_base_language_when_multilanguage_is_disabled(): void
    {
        $this->seed(LanguageSeeder::class);
        config(['localization.multilanguage_enabled' => false]);

        $translations = MappingTarget::selectOptions()[__('pim.import.group.translations')];

        $this->assertCount(5, $translations);
    }
}
