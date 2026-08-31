<?php

namespace Modules\ImportGestionali\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\ImportGestionali\Filament\Pages\ImportProducts;
use Modules\ImportGestionali\Models\ImportRecord;
use Modules\ImportGestionali\Support\MappingTarget;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Taxonomies\Models\Taxonomy;
use Tests\TestCase;

class ImportProductsTaxonomyMappingTest extends TestCase
{
    use RefreshDatabase;

    private Taxonomy $colore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->colore = Taxonomy::create(['slug' => 'colore']);
        $this->colore->translations()->create(['locale' => 'it', 'name' => 'Colore']);
    }

    public function test_two_columns_mapped_to_the_same_taxonomy_are_rejected_by_name(): void
    {
        $page = Livewire::test(ImportProducts::class)
            ->set('data.mapping', [
                0 => 'sku',
                1 => MappingTarget::forTaxonomy($this->colore->id),
                2 => MappingTarget::forTaxonomy($this->colore->id),
            ])
            ->instance();

        try {
            $page->assertMappingValid();
            $this->fail('expected a validation error');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Colore', $e->validator->errors()->first('data.mapping'));
        }
    }

    public function test_the_toggles_are_saved_on_the_import_record(): void
    {
        $key = MappingTarget::forTaxonomy($this->colore->id);
        $this->colore->terms()->create(['slug' => 'rosso'])
            ->translations()->create(['locale' => 'it', 'name' => 'Rosso']);

        Livewire::test(ImportProducts::class)
            ->set('data.file', UploadedFile::fake()->createWithContent(
                'listino.csv',
                "Codice;Nome;Colore\nA1;Sedia;Rosso\n",
            ))
            ->set('data.mapping', [0 => 'sku', 1 => 'name', 2 => $key])
            ->set('data.create_missing_terms', true)
            ->set('data.replace_taxonomy_terms', true)
            ->call('import')
            ->assertRedirect();

        $record = ImportRecord::sole();
        $this->assertTrue($record->create_missing_terms);
        $this->assertTrue($record->replace_taxonomy_terms);
        $this->assertSame($key, $record->mapping[2]);
    }

    public function test_the_preview_shows_the_taxonomy_resolution(): void
    {
        $this->colore->terms()->create(['slug' => 'rosso'])
            ->translations()->create(['locale' => 'it', 'name' => 'Rosso']);

        $rows = Livewire::test(ImportProducts::class)
            ->set('fileHeader', ['Codice', 'Nome', 'Colore'])
            ->set('sampleRows', [['NEW', 'Sedia', 'Rosso|Verde']])
            ->set('data.mapping', [0 => 'sku', 1 => 'name', 2 => MappingTarget::forTaxonomy($this->colore->id)])
            ->instance()
            ->previewRows();

        $resolution = $rows[0]['outcome']->taxonomies[0];
        $this->assertSame('Colore', $resolution->taxonomyName);
        $this->assertSame('found', $resolution->terms[0]['status']);
        $this->assertSame('missing', $resolution->terms[1]['status']);
    }
}
