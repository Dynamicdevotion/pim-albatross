<?php

namespace Modules\WooSync\Exceptions;

/**
 * The store could not be contacted at all: DNS failure, connection refused,
 * TLS error or timeout. Nothing was sent.
 */
class StoreUnreachable extends WooSyncException {}
