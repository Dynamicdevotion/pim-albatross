<?php

return [
    'name' => 'Products',

    /*
     * The "stock basso" products-list filter matches a positive stock at or
     * below this value. Override via config or a PRODUCTS_LOW_STOCK env var.
     */
    'low_stock_threshold' => (int) env('PRODUCTS_LOW_STOCK', 5),
];
