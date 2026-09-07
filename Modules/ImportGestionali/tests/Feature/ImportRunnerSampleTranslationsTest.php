<?php

namespace Modules\ImportGestionali\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\ImportGestionali\Models\ImportRecord;
use Modules\ImportGestionali\Support\ImportRunner;
use Modules\ImportGestionali\Support\MappingTarget;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Tests\TestCase;

/**
 * Demonstrates the shipped sample file's "Nome EN" column (index 9, appended
 * after every pre-existing column so {@see ImportRunnerVariantsTest}'s own
 * hardcoded legacy mapping keeps working unchanged) mapped through the new
 * dynamic `translation:{languageId}:name` target — see {@see MappingTarget}.
 */
class ImportRunnerSampleTranslationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        Storage::fake('local');
    }

    public function test_the_shipped_sample_files_english_column_imports_alongside_the_base_language(): void
    {
        $path = base_path('Modules/ImportGestionali/resources/samples/prodotti_gioielleria_test.csv');
        Storage::disk('local')->put('imports/sample.csv', file_get_contents($path));

        $englishId = Locales::idFor('en');

        $record = ImportRecord::factory()->create([
            'original_filename' => 'prodotti_gioielleria_test.csv',
            'stored_path' => 'imports/sample.csv',
            'status' => 'pending',
            'update_existing' => false,
            'mapping' => [
                0 => 'sku',
                1 => 'parent_sku',
                2 => MappingTarget::forTranslation(Locales::base()->id, 'name'),
                3 => MappingTarget::forTranslation(Locales::base()->id, 'description'),
                4 => 'price',
                5 => 'stock',
                6 => 'status',
                7 => null,
                8 => null,
                9 => MappingTarget::forTranslation($englishId, 'name'),
            ],
            'meta' => ['delimiter' => ',', 'encoding' => null],
            'total_rows' => 9,
        ]);

        app(ImportRunner::class)->run($record);
        $record->refresh();

        $this->assertSame('completed', $record->status);
        $this->assertSame(9, $record->created_count);
        $this->assertSame(0, $record->skipped_count);

        $necklace = Product::where('sku', 'GIO-COLLANA-01')->sole();
        $this->assertSame('Collana Perla Akoya', $necklace->translate('it')->name);
        $this->assertSame('Akoya Pearl Necklace', $necklace->translate('en')->name);
        $this->assertSame('akoya-pearl-necklace', $necklace->translate('en')->slug);

        $solitario = Product::where('sku', 'AN-SOLITARIO')->sole();
        $this->assertSame('Solitaire Ring', $solitario->translate('en')->name);

        // A variant row's "Nome EN" cell is blank: no English translation of
        // its own, since a non-base language needs a name to be created.
        $variant = Product::where('sku', 'AN-SOLIT-050-GIALLO')->sole();
        $this->assertNull($variant->translate('en'));
        $this->assertNotNull($variant->translate('it'));
    }
}
