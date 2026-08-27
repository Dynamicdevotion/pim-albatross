<?php

namespace Modules\Taxonomies\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class TaxonomiesPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'taxonomies';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: module_path('Taxonomies', 'app/Filament/Resources'),
            for: 'Modules\\Taxonomies\\Filament\\Resources',
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
