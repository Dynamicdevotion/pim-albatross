<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Tests\TestCase;

class AdminPanelConfigTest extends TestCase
{
    public function test_global_search_is_disabled(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertNull(Filament::getPanel('admin')->getGlobalSearchProvider());
    }
}
