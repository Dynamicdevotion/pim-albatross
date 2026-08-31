<?php

namespace Modules\WooSync\Exceptions;

use RuntimeException;

/**
 * Base for every WooCommerce API failure WooSync recognises. The message is
 * already translated and user-facing — ready to drop into a Filament
 * notification or a sync-report row. Named factory methods build the right
 * subclass and keep the wording in one place.
 */
class WooSyncException extends RuntimeException
{
    public static function unreachable(?string $detail = null): StoreUnreachable
    {
        return new StoreUnreachable(self::compose(__('pim.woosync.error.unreachable'), $detail));
    }

    public static function auth(?string $detail = null): AuthenticationFailed
    {
        return new AuthenticationFailed(self::compose(__('pim.woosync.error.auth'), $detail));
    }

    public static function rateLimited(?int $retryAfter = null): RateLimited
    {
        $exception = new RateLimited(__('pim.woosync.error.rate_limited'));
        $exception->retryAfter = $retryAfter;

        return $exception;
    }

    public static function rejected(string $detail): RequestRejected
    {
        return new RequestRejected(self::compose(__('pim.woosync.error.rejected'), $detail));
    }

    public static function gone(?string $detail = null): ResourceGone
    {
        return new ResourceGone(self::compose(__('pim.woosync.error.gone'), $detail));
    }

    public static function storeError(?string $detail = null): StoreError
    {
        return new StoreError(self::compose(__('pim.woosync.error.store_error'), $detail));
    }

    private static function compose(string $message, ?string $detail): string
    {
        $detail = $detail !== null ? trim($detail) : '';

        return $detail === '' ? $message : $message.' ('.$detail.')';
    }
}
