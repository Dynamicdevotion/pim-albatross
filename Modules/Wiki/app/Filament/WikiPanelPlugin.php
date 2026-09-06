<?php

namespace Modules\Wiki\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Registers the "Guida" page. Unlike WooSync, the wiki itself has no
 * commercial flag — it's core infrastructure, always available. What varies
 * per installation is which *sections* it shows: {@see
 * \Modules\Wiki\Support\DocTree} filters those out by consulting each
 * optional module's own feature flag (e.g. {@see
 * \Modules\WooSync\Support\WooSync::enabled()}) through
 * config('wiki.section_visibility').
 */
class WikiPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'wiki';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverPages(
            in: module_path('Wiki', 'app/Filament/Pages'),
            for: 'Modules\\Wiki\\Filament\\Pages',
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
