<?php

namespace Modules\WooSync\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Products\Support\ProductRowActions;
use Modules\WooSync\Filament\Pages\ManageWooSync;
use Modules\WooSync\Filament\Resources\WooSyncRuns\WooSyncRunResource;
use Modules\WooSync\Providers\WooSyncServiceProvider;
use Modules\WooSync\Support\WooSync;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_mirrors_the_config_value(): void
    {
        config(['woosync.enabled' => true]);
        $this->assertTrue(WooSync::enabled());

        config(['woosync.enabled' => false]);
        $this->assertFalse(WooSync::enabled());
    }

    public function test_product_actions_are_only_wired_when_enabled(): void
    {
        ProductRowActions::flush();
        config(['woosync.enabled' => false]);
        WooSyncServiceProvider::registerProductActions();
        $this->assertSame([], ProductRowActions::record());
        $this->assertSame([], ProductRowActions::bulk());

        ProductRowActions::flush();
        config(['woosync.enabled' => true]);
        WooSyncServiceProvider::registerProductActions();
        $this->assertNotSame([], ProductRowActions::record());
        $this->assertNotSame([], ProductRowActions::bulk());

        ProductRowActions::flush();
    }

    public function test_the_panel_exposes_the_page_and_report_when_enabled(): void
    {
        // The suite runs with WOOSYNC_ENABLED=true (see phpunit.xml), so the
        // panel built at boot has discovered them.
        $panel = Filament::getPanel('admin');

        $this->assertContains(ManageWooSync::class, $panel->getPages());
        $this->assertContains(WooSyncRunResource::class, $panel->getResources());
    }
}
