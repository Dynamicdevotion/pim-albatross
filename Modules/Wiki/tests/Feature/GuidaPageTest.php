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

    public function test_search_highlights_the_matched_term_in_the_sidebar(): void
    {
        Livewire::test(GuidaPage::class)
            ->set('search', 'varianti')
            ->assertSee('<mark>Varianti</mark>', false);
    }

    public function test_breadcrumbs_go_from_guida_to_the_section_to_the_page(): void
    {
        $component = Livewire::test(GuidaPage::class)
            ->set('activePage', '02-prodotti/varianti')
            ->instance();

        $breadcrumbs = $component->getBreadcrumbs();

        $this->assertSame(['Prodotti', 'Varianti'], array_values(array_slice($breadcrumbs, 1)));
    }

    public function test_breadcrumbs_stop_at_the_section_when_its_own_index_page_is_active(): void
    {
        $component = Livewire::test(GuidaPage::class)
            ->set('activePage', '02-prodotti')
            ->instance();

        $breadcrumbs = $component->getBreadcrumbs();

        $this->assertSame(['Prodotti'], array_values(array_slice($breadcrumbs, 1)));
    }

    public function test_table_of_contents_is_empty_for_pages_without_h2_or_h3_headings(): void
    {
        $component = Livewire::test(GuidaPage::class)
            ->set('activePage', '02-prodotti/varianti')
            ->instance();

        $this->assertSame([], $component->pageToc);
    }

    public function test_the_inactive_badge_renders_next_to_the_woocommerce_section_when_woosync_is_off(): void
    {
        config(['woosync.enabled' => false]);

        Livewire::test(GuidaPage::class)->assertSee(__('pim.wiki.badge.inactive'));
    }

    public function test_the_inactive_badge_does_not_render_when_woosync_is_on(): void
    {
        config(['woosync.enabled' => true]);

        Livewire::test(GuidaPage::class)->assertDontSee(__('pim.wiki.badge.inactive'));
    }
}
