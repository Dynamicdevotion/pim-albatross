<?php

namespace Modules\Pricing\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

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
