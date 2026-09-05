<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Modules\ImportGestionali\Support\FieldGuesser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FieldGuesserTest extends TestCase
{
    #[DataProvider('headers')]
    public function test_it_guesses_the_field_from_a_header(string $header, ?string $expected): void
    {
        $this->assertSame($expected, FieldGuesser::guess($header));
    }

    public static function headers(): array
    {
        return [
            ['Codice', 'sku'],
            ['SKU', 'sku'],
            ['Codice Articolo', 'sku'],
            ['Codice Padre', 'parent_sku'],
            ['SKU Padre', 'parent_sku'],
            ['Parent SKU', 'parent_sku'],
            ['Nome', 'name'],
            ['Descrizione', 'name'],
            ['Descrizione estesa', 'description'],
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

        $this->assertSame('sku', $mapping[0]);
        $this->assertSame('', $mapping[1], 'sku is already taken by the first column');
        $this->assertSame('name', $mapping[2]);
        $this->assertSame('price', $mapping[3]);
    }

    public function test_for_header_keeps_sku_and_parent_sku_apart(): void
    {
        $mapping = FieldGuesser::forHeader(['Codice', 'Codice Padre', 'Nome']);

        $this->assertSame('sku', $mapping[0]);
        $this->assertSame('parent_sku', $mapping[1]);
        $this->assertSame('name', $mapping[2]);
    }
}
