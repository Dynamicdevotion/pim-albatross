<?php

namespace Modules\WooSync\Exceptions;

/**
 * The store rejected the credentials (HTTP 401 / 403) — missing, wrong, revoked
 * or read-only consumer key / secret — or the connection is not configured yet.
 */
class AuthenticationFailed extends WooSyncException {}
