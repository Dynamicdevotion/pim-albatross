<?php

namespace Modules\WooSync\Support;

use Laravel\Pennant\Feature;
use Modules\WooSync\Filament\WooSyncPanelPlugin;
use Modules\WooSync\Providers\WooSyncServiceProvider;

/**
 * The commercial on/off switch for the whole module. WooSync is sold as a
 * separate add-on: every seam it contributes — the connection Settings page,
 * the sync report resource, the product row / bulk actions, the module routes —
 * is gated on this.
 *
 * The value comes from `config('woosync.enabled')` (in turn `WOOSYNC_ENABLED`
 * in the installation's .env). It is read straight from config rather than
 * through Pennant because {@see WooSyncPanelPlugin}
 * consults it while the panel is being registered, which can run before the
 * module's service provider has defined the Pennant feature. A matching
 * Pennant feature named {@see self::FEATURE} is still defined from the same
 * value in {@see WooSyncServiceProvider::boot()}, so
 * `Feature::active('woosync')`, the `@feature` Blade directive and any future
 * per-tenant scoping keep working.
 */
final class WooSync
{
    public const FEATURE = 'woosync';

    public static function enabled(): bool
    {
        return (bool) config('woosync.enabled');
    }

    /**
     * Register the Pennant feature mirroring {@see self::enabled()}. Called
     * once from the service provider.
     */
    public static function defineFeature(): void
    {
        Feature::define(self::FEATURE, static fn (): bool => self::enabled());
    }
}
