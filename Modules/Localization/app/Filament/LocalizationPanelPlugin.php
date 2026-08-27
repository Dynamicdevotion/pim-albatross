<?php

namespace Modules\Localization\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class LocalizationPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'localization';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: module_path('Localization', 'app/Filament/Resources'),
            for: 'Modules\\Localization\\Filament\\Resources',
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
