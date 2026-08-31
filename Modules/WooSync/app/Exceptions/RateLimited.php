<?php

namespace Modules\WooSync\Exceptions;

/**
 * The store asked us to slow down (HTTP 429). A bulk sync stops when this is
 * raised — what already completed is kept and the rest can be re-run later.
 */
class RateLimited extends WooSyncException
{
    /** Seconds to wait before retrying, from the `Retry-After` header, if any. */
    public ?int $retryAfter = null;
}
