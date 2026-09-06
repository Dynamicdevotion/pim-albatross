<?php

namespace Modules\Wiki\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Wiki\Filament\Pages\GuidaPage;
use Tests\TestCase;

class GuidaPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_page_renders_and_defaults_to_the_first_section(): void
    {
        $response = $this->get('/admin/guida');

        $response->assertSuccessful();
        $response->assertSee('Primi passi');
    }

    public function test_selecting_a_page_renders_its_markdown_as_html(): void
    {
        Livewire::test(GuidaPage::class)
            ->set('activePage', '02-prodotti/varianti')
            ->assertSee('Varianti')
            ->assertSee('<table', false);
    }

    public function test_search_narrows_the_sidebar_without_changing_the_open_page(): void
    {
        Livewire::test(GuidaPage::class)
            ->set('activePage', '02-prodotti/varianti')
            ->set('search', 'woocommerce')
            ->assertSee('Varianti');
    }
}
