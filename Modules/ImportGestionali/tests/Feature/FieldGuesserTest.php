<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ImportGestionali\Support\FieldGuesser;
use Modules\ImportGestionali\Support\MappingTarget;
use Modules\Localization\Support\Locales;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FieldGuesserTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('headers')]
    public function test_it_guesses_the_field_from_a_header(string $header, ?string $expectedField): void
    {
        $expected = $expectedField === null ? null : MappingTarget::forTranslation(Locales::base()->id, $expectedField);

        $this->assertSame($expected, FieldGuesser::guess($header));
    }

    /**
     * @return array<int, array{0: string, 1: ?string}>
     */
    public static function headers(): array
    {
        return [
            ['Nome', 'name'],
            ['Descrizione', 'name'],
            ['Descrizione estesa', 'description'],
            ['Meta Title', 'meta_title'],
            ['SEO Title', 'meta_title'],
            ['Meta Description', 'meta_description'],
            ['Slug', 'slug'],
            ['URL SEO', 'slug'],
        ];
    }

    #[DataProvider('nonTranslatableHeaders')]
    public function test_it_guesses_non_translatable_fields_as_bare_strings(string $header, ?string $expected): void
    {
        $this->assertSame($expected, FieldGuesser::guess($header));
    }

    /**
     * @return array<int, array{0: string, 1: ?string}>
     */
    public static function nonTranslatableHeaders(): array
    {
        return [
            ['Codice', 'sku'],
            ['SKU', 'sku'],
            ['Codice Articolo', 'sku'],
            ['Codice Padre', 'parent_sku'],
            ['SKU Padre', 'parent_sku'],
            ['Parent SKU', 'parent_sku'],
            ['Prezzo Vendita', 'price'],
            ['Q.tà', 'stock'],
            ['Giacenza', 'stock'],
            ['Peso (kg)', 'weight'],
            ['Larghezza', 'width'],
            ['Stato', 'status'],
            ['Fornitore', null],
            ['', null],
        ];
    }

    public function test_for_header_assigns_each_field_once(): void
    {
        $mapping = FieldGuesser::forHeader(['Codice', 'SKU', 'Nome', 'Prezzo']);
        $nameTarget = MappingTarget::forTranslation(Locales::base()->id, 'name');

        $this->assertSame('sku', $mapping[0]);
        $this->assertSame('', $mapping[1], 'sku is already taken by the first column');
        $this->assertSame($nameTarget, $mapping[2]);
        $this->assertSame('price', $mapping[3]);
    }

    public function test_for_header_keeps_sku_and_parent_sku_apart(): void
    {
        $mapping = FieldGuesser::forHeader(['Codice', 'Codice Padre', 'Nome']);

        $this->assertSame('sku', $mapping[0]);
        $this->assertSame('parent_sku', $mapping[1]);
        $this->assertSame(MappingTarget::forTranslation(Locales::base()->id, 'name'), $mapping[2]);
    }
}
