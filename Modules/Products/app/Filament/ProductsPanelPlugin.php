<?php

namespace Modules\Products\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

class ProductsPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'products';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: module_path('Products', 'app/Filament/Resources'),
            for: 'Modules\Products\Filament\Resources',
        );

        // Styling for the bottom filter drawer on the products list page.
        FilamentAsset::register([
            Css::make('products-filters-drawer', module_path('Products', 'resources/css/products-filters-drawer.css')),
        ], package: 'products');
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
