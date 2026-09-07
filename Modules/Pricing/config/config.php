<?php

return [
    'name' => 'Pricing',

    /*
     * Commercial feature flag. With this off the panel behaves as if only
     * the default price list existed: the Price Lists page blocks creating
     * more than the default, the bulk price grid hides the list selector,
     * and the product/variant price table shows only the default list's row.
     * The gate is {@see \Modules\Pricing\Support\MultiplePriceListsFeature::enabled()};
     * a matching Laravel Pennant feature ("multiple_price_lists") is defined
     * from this same value.
     *
     * Set MULTIPLE_PRICE_LISTS_ENABLED=true in the installation's .env to turn it on.
     */
    'multiple_price_lists_enabled' => (bool) env('MULTIPLE_PRICE_LISTS_ENABLED', false),
];
