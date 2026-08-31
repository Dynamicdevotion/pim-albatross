<?php

return [
    'name' => 'Dashboard',

    /*
     * Slug of the taxonomy whose terms drive the "products by category" chart.
     * Leave empty to auto-detect: the panel-created taxonomy is "Categorie"
     * (slug `categorie`), earlier seeded data used `categoria`, so the widget
     * falls back to the first taxonomy whose slug starts with `categor`.
     */
    'category_taxonomy_slug' => env('DASHBOARD_CATEGORY_TAXONOMY'),
];
