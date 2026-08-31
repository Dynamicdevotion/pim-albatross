<?php

namespace Modules\WooSync\Exceptions;

/**
 * The store understood the request but refused it (HTTP 400 / 422 and other
 * 4xx): invalid product data, a duplicate SKU, a missing required field. The
 * detail carries WooCommerce's own `message` from the response body.
 */
class RequestRejected extends WooSyncException {}
