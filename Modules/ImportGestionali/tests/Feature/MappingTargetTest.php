<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ImportGestionali\Support\MappingTarget;
use Modules\Localization\Database\Seeders\LanguageSeeder;
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

    public function test_label_resolves_fixed_fields_and_taxonomies(): void
    {
        $this->assertSame(__('pim.import.field.sku'), MappingTarget::label('sku'));
        $this->assertSame('Colore', MappingTarget::label('taxonomy:5', [5 => 'Colore']));
        $this->assertSame('taxonomy:9', MappingTarget::label('taxonomy:9', []));
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
}
