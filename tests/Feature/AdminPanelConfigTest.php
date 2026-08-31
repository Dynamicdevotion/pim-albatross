<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Modules\Localization\Filament\Resources\Languages\LanguageResource;
use Modules\Products\Filament\Resources\Products\ProductResource;
use Modules\Taxonomies\Filament\Resources\Taxonomies\TaxonomyResource;
use Tests\TestCase;

class AdminPanelConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_global_search_is_disabled(): void
    {
        $this->assertNull(Filament::getPanel('admin')->getGlobalSearchProvider());
    }

    public function test_navigation_group_order(): void
    {
        $groups = Filament::getPanel('admin')->getNavigationGroups();
        $labels = array_map(fn ($group): string => is_string($group) ? $group : $group->getLabel(), $groups);

        // The WooSync group sits between Export and Impostazioni; it is present
        // here because the test suite runs with WOOSYNC_ENABLED=true, and is
        // simply hidden (empty) on an installation without the add-on.
        $this->assertSame([
            __('pim.nav.pricing'),
            __('pim.import.nav.group'),
            __('pim.export.nav.group'),
            __('pim.woosync.nav.group'),
            __('pim.branding.nav.group'),
        ], $labels);
    }

    public function test_ungrouped_resources_sort_before_the_groups(): void
    {
        // Dashboard first (-2), then Products, Taxonomies, Languages.
        $this->assertSame(-2, Dashboard::getNavigationSort());
        $this->assertSame(10, ProductResource::getNavigationSort());
        $this->assertSame(20, TaxonomyResource::getNavigationSort());
        $this->assertSame(30, LanguageResource::getNavigationSort());
    }
}
