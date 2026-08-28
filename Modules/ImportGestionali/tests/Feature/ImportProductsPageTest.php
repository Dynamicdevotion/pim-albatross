<?php

namespace Modules\ImportGestionali\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\ImportGestionali\Filament\Pages\ImportProducts;
use Modules\ImportGestionali\Jobs\RunProductImport;
use Modules\ImportGestionali\Models\ImportRecord;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;
use Tests\TestCase;

class ImportProductsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        PriceList::create(['name' => 'Standard', 'is_default' => true]);
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_wizard_page_mounts(): void
    {
        Livewire::test(ImportProducts::class)->assertSuccessful();
    }

    public function test_mapping_without_sku_is_rejected(): void
    {
        $page = Livewire::test(ImportProducts::class)
            ->set('data.mapping', [0 => 'name', 1 => 'price'])
            ->instance();

        $this->expectException(ValidationException::class);
        $page->assertMappingValid();
    }

    public function test_a_field_mapped_twice_is_rejected(): void
    {
        $page = Livewire::test(ImportProducts::class)
            ->set('data.mapping', [0 => 'sku', 1 => 'price', 2 => 'price'])
            ->instance();

        $this->expectException(ValidationException::class);
        $page->assertMappingValid();
    }

    public function test_preview_reports_the_dry_run_outcome_of_each_sample_row(): void
    {
        Product::factory()->create(['sku' => 'DUP']);

        $rows = Livewire::test(ImportProducts::class)
            ->set('fileHeader', ['Codice', 'Nome', 'Prezzo'])
            ->set('sampleRows', [
                ['NEW', 'Sedia', '10'],
                ['DUP', 'Gia', '5'],
                ['', 'Senza', '1'],
            ])
            ->set('data.mapping', [0 => 'sku', 1 => 'name', 2 => 'price'])
            ->set('data.update_existing', false)
            ->instance()
            ->previewRows();

        $this->assertSame('created', $rows[0]['outcome']->action);
        $this->assertSame('skipped', $rows[1]['outcome']->action);
        $this->assertStringContainsString('già presente', $rows[1]['outcome']->reason);
        $this->assertSame('skipped', $rows[2]['outcome']->action);
        $this->assertSame(0, Product::where('sku', 'NEW')->count(), 'the preview must not write');
    }

    public function test_a_small_import_runs_inline_and_redirects_to_the_report(): void
    {
        Storage::disk('local')->put('imports/small.csv', "Codice;Nome;Prezzo\nA1;Sedia;10\nA2;Tavolo;20\n");

        Livewire::test(ImportProducts::class)
            ->set('data.file', 'imports/small.csv')
            ->set('data.file_original_names', ['imports/small.csv' => 'listino.csv'])
            ->call('inspectFile', 'imports/small.csv')
            ->set('data.mapping', [0 => 'sku', 1 => 'name', 2 => 'price'])
            ->call('import')
            ->assertRedirect();

        $record = ImportRecord::sole();
        $this->assertSame('completed', $record->status);
        $this->assertSame(2, $record->created_count);
        $this->assertSame('listino.csv', $record->original_filename);
        $this->assertSame(2, Product::count());
    }

    public function test_an_import_over_the_threshold_is_queued(): void
    {
        Queue::fake();

        $csv = "Codice;Nome\n";
        for ($i = 1; $i <= 350; $i++) {
            $csv .= "SKU{$i};Prodotto {$i}\n";
        }
        Storage::disk('local')->put('imports/big.csv', $csv);

        Livewire::test(ImportProducts::class)
            ->set('data.file', 'imports/big.csv')
            ->call('inspectFile', 'imports/big.csv')
            ->set('data.mapping', [0 => 'sku', 1 => 'name'])
            ->call('import')
            ->assertRedirect();

        Queue::assertPushed(RunProductImport::class);
        $this->assertSame('pending', ImportRecord::sole()->status);
        $this->assertSame(350, ImportRecord::sole()->total_rows);
    }
}
