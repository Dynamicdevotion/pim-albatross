<?php

namespace Modules\ImportGestionali\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\ImportGestionali\Filament\Resources\ImportRecords\Pages\ListImportRecords;
use Modules\ImportGestionali\Models\ImportRecord;
use Tests\TestCase;

class ImportRecordsResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('local');
    }

    public function test_deleting_a_record_removes_its_stored_source_file(): void
    {
        Storage::disk('local')->put('imports/gone.csv', "Codice\nA1\n");

        $record = ImportRecord::factory()->create([
            'status' => 'completed',
            'stored_path' => 'imports/gone.csv',
            'mapping' => [0 => 'sku'],
        ]);

        $record->delete();

        Storage::disk('local')->assertMissing('imports/gone.csv');
    }

    public function test_the_history_list_exposes_a_delete_action(): void
    {
        $record = ImportRecord::factory()->create(['status' => 'completed', 'mapping' => [0 => 'sku']]);

        Livewire::test(ListImportRecords::class)
            ->assertTableActionVisible('delete', $record);
    }
}
