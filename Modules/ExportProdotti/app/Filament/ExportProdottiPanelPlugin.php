<?php

namespace Modules\ExportProdotti\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class ExportProdottiPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'exportprodotti';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: module_path('ExportProdotti', 'app/Filament/Resources'),
            for: 'Modules\\ExportProdotti\\Filament\\Resources',
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
