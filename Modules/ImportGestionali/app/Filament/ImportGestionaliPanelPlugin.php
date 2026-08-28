<?php

namespace Modules\ImportGestionali\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class ImportGestionaliPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'importgestionali';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverResources(
                in: module_path('ImportGestionali', 'app/Filament/Resources'),
                for: 'Modules\\ImportGestionali\\Filament\\Resources',
            )
            ->discoverPages(
                in: module_path('ImportGestionali', 'app/Filament/Pages'),
                for: 'Modules\\ImportGestionali\\Filament\\Pages',
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
