<?php

/*
 * This lives at the app level (not only in Modules/WooSync/config/config.php)
 * on purpose: Laravel loads every config/*.php before any service provider
 * registers, and it is baked into `php artisan config:cache`. WooSync's
 * commercial gate is checked while the Filament panel is being built — i.e.
 * before the module's own service provider merges its config — so the value
 * must already be here. Keep the two files in sync.
 */

return [
    'name' => 'WooSync',

    /*
     * Commercial feature flag. WooSync is sold separately from the rest of the
     * PIM: with this off the module contributes nothing to the panel — no
     * connection page, no sync report, no product actions, no routes. The gate
     * is \Modules\WooSync\Support\WooSync::enabled(); a matching Laravel
     * Pennant feature ("woosync") is defined from this same value so
     * Feature::active('woosync') / @feature work too.
     *
     * Set WOOSYNC_ENABLED=true in the installation's .env to turn it on.
     */
    'enabled' => (bool) env('WOOSYNC_ENABLED', false),

    /*
     * A bulk "Sincronizza con WooCommerce" of this many products or fewer runs
     * inline in the request; larger ones are pushed onto the queue (needs the
     * scheduled `queue:work --stop-when-empty`, like ImportGestionali /
     * ExportProdotti). Each product is at least one HTTP round-trip to the
     * store, so this is far lower than the export's row threshold.
     */
    'inline_max_products' => (int) env('WOOSYNC_INLINE_MAX', 25),

    /*
     * HTTP timeout, in seconds, for a single WooCommerce REST call.
     */
    'timeout' => (int) env('WOOSYNC_TIMEOUT', 20),

    /*
     * Pause between consecutive product pushes during a bulk sync, in
     * milliseconds, to stay under the store's rate limit.
     */
    'request_delay_ms' => (int) env('WOOSYNC_REQUEST_DELAY_MS', 250),
];
