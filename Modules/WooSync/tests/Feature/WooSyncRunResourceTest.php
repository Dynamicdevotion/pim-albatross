<?php

namespace Modules\WooSync\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\WooSync\Filament\Resources\WooSyncRuns\Pages\ListWooSyncRuns;
use Modules\WooSync\Filament\Resources\WooSyncRuns\Pages\ViewWooSyncRun;
use Modules\WooSync\Filament\Resources\WooSyncRuns\WooSyncRunResource;
use Modules\WooSync\Models\WooSyncRun;
use Tests\TestCase;

class WooSyncRunResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_report_is_read_only(): void
    {
        $this->assertFalse(WooSyncRunResource::canCreate());
    }

    public function test_the_list_and_view_pages_render_a_run_with_its_items(): void
    {
        $run = WooSyncRun::factory()->completed()->create([
            'trigger' => 'bulk',
            'total' => 2,
            'created_count' => 1,
            'failed_count' => 1,
            'items' => [
                ['product' => 'Sedia', 'sku' => 'S1', 'result' => 'created', 'reason' => null],
                ['product' => 'Tavolo', 'sku' => 'T1', 'result' => 'failed', 'reason' => 'Negozio irraggiungibile.'],
            ],
        ]);

        Livewire::test(ListWooSyncRuns::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$run]);

        Livewire::test(ViewWooSyncRun::class, ['record' => $run->getKey()])
            ->assertOk()
            ->assertSee('Sedia')
            ->assertSee('Tavolo')
            ->assertSee('Negozio irraggiungibile.');
    }
}
