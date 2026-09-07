<?php

namespace Modules\Localization\Support;

use Laravel\Pennant\Feature;
use Modules\WooSync\Support\WooSync;

/**
 * The commercial on/off switch for multi-language content. Sold as a Pro
 * upgrade: with it off, the panel behaves as if only the base language
 * (`is_base = true`) existed, regardless of how many other languages are
 * technically marked `active` in the database — see {@see Locales::active()},
 * the single seam every translation tab, filter and export already reads
 * through, so gating it there is enough to make the whole panel comply
 * without hunting down each call site individually.
 *
 * Unlike {@see WooSync}, this reads straight through
 * Pennant (`Feature::active()`) rather than bypassing it via `config()`:
 * WooSync has to avoid Pennant because its panel plugin decides whether to
 * register whole pages/resources at panel-boot time, before the module's
 * service provider has defined the feature. Nothing here gates panel
 * registration — Languages/Products/Taxonomies are always registered, they
 * just behave differently at runtime — so there's no ordering problem, and
 * Pennant's default `array` store (see config/pennant.php) re-evaluates the
 * resolver on every request anyway, so there's no staleness risk either.
 */
final class MultilanguageFeature
{
    public const FEATURE = 'multilanguage';

    public static function enabled(): bool
    {
        return Feature::active(self::FEATURE);
    }

    /**
     * Register the Pennant feature. Called once from the service provider.
     */
    public static function defineFeature(): void
    {
        Feature::define(self::FEATURE, static fn (): bool => (bool) config('localization.multilanguage_enabled'));
    }
}
