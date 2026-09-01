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
     * The store product with this id. Throws a `ResourceGone` (a
     * {@see WooSyncException} subclass) if it no longer exists on the store.
     *
     * @return array<string, mixed>
     */
    public function getProduct(int $wooId): array;

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
     * Delete a store product. With `force: false` (the default) this moves it
     * to the WooCommerce trash rather than deleting it permanently.
     *
     * @throws WooSyncException
     */
    public function deleteProduct(int $wooId, bool $force = false): void;

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

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function listAttributes(array $query = []): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createAttribute(array $payload): array;

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function listAttributeTerms(int $attributeId, array $query = []): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createAttributeTerm(int $attributeId, array $payload): array;

    /**
     * The store variation with this id, under the given parent product.
     * Throws `ResourceGone` (404) exactly like {@see getProduct()}.
     *
     * @return array<string, mixed>
     */
    public function getVariation(int $productId, int $variationId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createVariation(int $productId, array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateVariation(int $productId, int $variationId, array $payload): array;

    /**
     * Delete a variation. With `force: false` (the default) this moves it to
     * the WooCommerce trash rather than deleting it permanently.
     *
     * @throws WooSyncException
     */
    public function deleteVariation(int $productId, int $variationId, bool $force = false): void;
}
