<?php

namespace Modules\WooSync\Contracts;

use Modules\WooSync\Exceptions\WooSyncException;
use Modules\WooSync\Support\Http\BasicAuthWooClient;

/**
 * The slice of the WooCommerce REST API (v3) that WooSync uses. One
 * implementation today — {@see BasicAuthWooClient},
 * Basic Auth over HTTPS — but the runner and payload builders depend only on
 * this contract, so a plain-HTTP store's OAuth 1.0a signing, or the official
 * `automattic/woocommerce` SDK, could be swapped in without touching them.
 *
 * Every method throws a {@see WooSyncException}
 * subclass on any failed call (unreachable store, bad credentials, rate limit,
 * rejected payload); it never returns a partial or empty result to signal an
 * error.
 */
interface WooCommerceClient
{
    /**
     * Lightweight reachability + credentials probe (GET /system_status).
     *
     * @throws WooSyncException
     */
    public function ping(): void;

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function listProducts(array $query = []): array;

    /**
     * The first store product with this exact SKU, or null.
     *
     * @return array<string, mixed>|null
     */
    public function findProductBySku(string $sku): ?array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createProduct(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateProduct(int $wooId, array $payload): array;

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function listCategories(array $query = []): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCategory(array $payload): array;
}
