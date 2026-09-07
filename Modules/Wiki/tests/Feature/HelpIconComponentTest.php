<?php

namespace Modules\Wiki\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Modules\Wiki\Filament\Pages\GuidaPage;
use Tests\TestCase;

class HelpIconComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_it_links_to_the_given_page_and_anchor_in_a_new_tab(): void
    {
        $html = Blade::render('<x-wiki::help-icon page="02-prodotti/varianti" anchor="come-generare-varianti" />');

        $expectedUrl = GuidaPage::getUrl(['p' => '02-prodotti/varianti']).'#come-generare-varianti';

        $this->assertStringContainsString('href="'.$expectedUrl.'"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
    }

    public function test_it_links_to_the_page_alone_when_no_anchor_is_given(): void
    {
        $html = Blade::render('<x-wiki::help-icon page="02-prodotti" />');

        $expectedUrl = GuidaPage::getUrl(['p' => '02-prodotti']);

        $this->assertStringContainsString('href="'.$expectedUrl.'"', $html);
    }
}
