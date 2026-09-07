<?php

return [
    'name' => 'Localization',

    /*
     * Commercial feature flag. With this off the panel behaves as if only
     * the base language existed: the Languages page blocks activating more
     * than the base, translation tabs on the product/taxonomy forms collapse
     * to the base language only, and the "missing translation" filter is
     * hidden. The gate is {@see \Modules\Localization\Support\MultilanguageFeature::enabled()};
     * a matching Laravel Pennant feature ("multilanguage") is defined from
     * this same value.
     *
     * Set MULTILANGUAGE_ENABLED=true in the installation's .env to turn it on.
     */
    'multilanguage_enabled' => (bool) env('MULTILANGUAGE_ENABLED', false),
];
