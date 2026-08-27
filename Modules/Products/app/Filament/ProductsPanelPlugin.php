<?php

namespace Modules\Products\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

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
