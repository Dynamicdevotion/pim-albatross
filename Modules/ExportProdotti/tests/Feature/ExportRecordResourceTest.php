<?php

namespace Modules\ExportProdotti\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\ExportProdotti\Filament\Resources\ExportRecords\Pages\ListExportRecords;
use Modules\ExportProdotti\Filament\Resources\ExportRecords\Pages\ViewExportRecord;
use Modules\ExportProdotti\Models\ExportRecord;
use Tests\TestCase;

class ExportRecordResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('local');
    }

    public function test_the_history_list_renders_a_run(): void
    {
        $record = ExportRecord::factory()->completed()->create();

        Livewire::test(ListExportRecords::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$record]);
    }

    public function test_a_completed_run_offers_the_download_action(): void
    {
        Storage::disk('local')->put('exports/done.csv', "SKU\nA\n");

        $record = ExportRecord::factory()->completed()->create([
            'format' => 'csv',
            'stored_path' => 'exports/done.csv',
            'original_filename' => 'export-prodotti.csv',
        ]);

        Livewire::test(ViewExportRecord::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertActionVisible('download')
            ->callAction('download')
            ->assertFileDownloaded('export-prodotti.csv');
    }

    public function test_a_running_run_hides_the_download_action(): void
    {
        $record = ExportRecord::factory()->create(['status' => 'processing']);

        Livewire::test(ViewExportRecord::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertActionHidden('download');
    }
}
