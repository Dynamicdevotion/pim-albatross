<?php

namespace Modules\Dashboard\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Dashboard\Filament\Widgets\ProductOverviewStats;
use Modules\Dashboard\Filament\Widgets\ProductsByCategoryChart;
use Modules\Dashboard\Filament\Widgets\ProductsMissingImage;
use Modules\Dashboard\Filament\Widgets\RecentImportIssues;

/**
 * Replaces the stock Filament dashboard widgets with a catalogue overview:
 * status numbers, two actionable lists and a category distribution chart.
 * The default `AccountWidget` / `FilamentInfoWidget` are dropped in
 * AdminPanelProvider.
 */
class DashboardPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashboard';
    }

    public function register(Panel $panel): void
    {
        $panel->widgets([
            ProductOverviewStats::class,
            ProductsByCategoryChart::class,
            ProductsMissingImage::class,
            RecentImportIssues::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
