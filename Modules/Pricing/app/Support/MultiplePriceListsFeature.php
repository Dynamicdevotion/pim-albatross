<?php

namespace Modules\Pricing\Support;

use Laravel\Pennant\Feature;
use Modules\Localization\Support\MultilanguageFeature;

/**
 * The commercial on/off switch for multiple price lists. Sold as a Pro
 * upgrade: with it off, the panel behaves as if only the default price list
 * existed, regardless of how many other lists are technically marked
 * `active` in the database — see {@see ProductPriceMatrix::activeLists()},
 * the single seam the product/variant price table and the bulk price grid
 * both read through.
 *
 * Same reasoning as {@see MultilanguageFeature}
 * for going through `Feature::active()` directly rather than WooSync's
 * config-bypass: Price Lists/Products pages are always registered in the
 * panel, nothing here needs the flag's value before the module's service
 * provider has defined it, and Pennant's default `array` store re-evaluates
 * the resolver fresh on every request.
 */
final class MultiplePriceListsFeature
{
    public const FEATURE = 'multiple_price_lists';

    public static function enabled(): bool
    {
        return Feature::active(self::FEATURE);
    }

    /**
     * Register the Pennant feature. Called once from the service provider.
     */
    public static function defineFeature(): void
    {
        Feature::define(self::FEATURE, static fn (): bool => (bool) config('pricing.multiple_price_lists_enabled'));
    }
}
