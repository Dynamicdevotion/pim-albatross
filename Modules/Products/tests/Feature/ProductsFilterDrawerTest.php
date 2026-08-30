<?php

namespace Modules\Products\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Localization\Database\Seeders\LanguageSeeder;
use Modules\Products\Filament\Resources\Products\Pages\ListProducts;
use Tests\TestCase;

class ProductsFilterDrawerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LanguageSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_list_page_renders_the_bottom_filter_drawer(): void
    {
        $html = Livewire::test(ListProducts::class)->html();

        // The custom bottom drawer shell and its trigger are present…
        $this->assertStringContainsString('pim-filters-drawer', $html);
        $this->assertStringContainsString('x-teleport', $html);
        $this->assertStringContainsString('filtersOpen = true', $html);
        $this->assertStringContainsString('filtersOpen = false', $html);

        // …it hosts the deferred table filter form…
        $this->assertStringContainsString('tableDeferredFilters', $html);

        // …and its footer drives Filament's own apply / reset handlers.
        $this->assertStringContainsString('applyTableFilters', $html);
        $this->assertStringContainsString('resetTableFiltersForm', $html);
    }

    public function test_applying_filters_from_the_drawer_updates_the_query(): void
    {
        // `applyTableFilters` is the exact handler the drawer's "Apply" button
        // calls; deferred filter state must reach the query through it.
        Livewire::test(ListProducts::class)
            ->set('tableDeferredFilters.status.value', 'archived')
            ->call('applyTableFilters')
            ->assertSet('tableFilters.status.value', 'archived');
    }
}
