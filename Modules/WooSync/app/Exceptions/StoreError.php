<?php

namespace Modules\WooSync\Exceptions;

/**
 * The store failed to handle the request (HTTP 5xx): a WordPress/WooCommerce
 * error, a plugin conflict, the host being overloaded. Retrying later may work.
 */
class StoreError extends WooSyncException {}
