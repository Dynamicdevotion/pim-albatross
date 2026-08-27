<?php

namespace Modules\Pricing\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;

class PricingPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'pricing';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverResources(
                in: module_path('Pricing', 'app/Filament/Resources'),
                for: 'Modules\\Pricing\\Filament\\Resources',
            )
            ->discoverPages(
                in: module_path('Pricing', 'app/Filament/Pages'),
                for: 'Modules\\Pricing\\Filament\\Pages',
            );

        $vendor = module_path('Pricing', 'resources/js/vendor');

        // jspreadsheet CE v4 (MIT) + jsuites (MIT), vendored — see resources/js/vendor.
        // jsuites must load before jspreadsheet; array order is preserved.
        FilamentAsset::register([
            Css::make('jsuites', $vendor.'/jsuites.css'),
            Css::make('jspreadsheet', $vendor.'/jspreadsheet.css'),
            Js::make('jsuites', $vendor.'/jsuites.js'),
            Js::make('jspreadsheet', $vendor.'/jspreadsheet.js'),
            Js::make('prices-grid', $vendor.'/prices-grid.js'),
        ], package: 'pricing');
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
