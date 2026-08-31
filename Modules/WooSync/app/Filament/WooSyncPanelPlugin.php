<?php

namespace Modules\WooSync\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\WooSync\Providers\WooSyncServiceProvider;
use Modules\WooSync\Support\WooSync;

/**
 * Registers WooSync's admin UI — the connection Settings page and the sync
 * report resource — but only when the commercial feature flag is on. With it
 * off the plugin adds nothing, so a client without the add-on sees no trace of
 * it (no pages, no nav group, no routes). The product row / bulk actions are
 * wired separately, in {@see WooSyncServiceProvider}.
 */
class WooSyncPanelPlugin implements Plugin
{
    public function getId(): string
    {
        return 'woosync';
    }

    public function register(Panel $panel): void
    {
        if (! WooSync::enabled()) {
            return;
        }

        $panel
            ->discoverResources(
                in: module_path('WooSync', 'app/Filament/Resources'),
                for: 'Modules\\WooSync\\Filament\\Resources',
            )
            ->discoverPages(
                in: module_path('WooSync', 'app/Filament/Pages'),
                for: 'Modules\\WooSync\\Filament\\Pages',
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
