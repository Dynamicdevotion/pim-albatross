<?php

namespace Modules\WooSync\Exceptions;

/**
 * The store resource is not there (HTTP 404) — typically a linked product that
 * was deleted on the WooCommerce side. The runner treats this as a signal to
 * drop the stale link and recreate the product.
 */
class ResourceGone extends WooSyncException {}
