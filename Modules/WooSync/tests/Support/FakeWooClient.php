<?php

namespace Modules\WooSync\Tests\Support;

use Closure;
use Modules\WooSync\Contracts\WooCommerceClient;

/**
 * In-memory {@see WooCommerceClient} for the runner / action tests: records
 * every call and the create / update payloads, serves canned data, and lets a
 * test inject failures through the `onGetProduct` / `onCreateProduct` /
 * `onUpdateProduct` hooks.
 *
 * `createProduct` / `updateProduct` echo back the payload they were given
 * (merged over the stored product for an update), so a test drives the stock
 * scenario purely through `productsById` and the product's own `stock`.
 */
class FakeWooClient implements WooCommerceClient
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<array<string, mixed>> */
    public array $createdProducts = [];

    /** @var list<array<string, mixed>> */
    public array $createdCategories = [];

    /** @var list<array<string, mixed>> */
    public array $createPayloads = [];

    /** @var array<int, array<string, mixed>> keyed by woo id */
    public array $updatePayloads = [];

    /** @var list<int> woo ids passed to deleteProduct, in call order */
    public array $deletedIds = [];

    /** @var array<string, array<string, mixed>> keyed by SKU */
    public array $productsBySku = [];

    /** @var array<int, array<string, mixed>> keyed by woo id */
    public array $productsById = [];

    /** @var list<array<string, mixed>> */
    public array $categories = [];

    public int $nextId = 100;

    /** @var (Closure(int): array<string, mixed>)|null */
    public ?Closure $onGetProduct = null;

    /** @var (Closure(array<string, mixed>): array<string, mixed>)|null */
    public ?Closure $onCreateProduct = null;

    /** @var (Closure(int, array<string, mixed>): array<string, mixed>)|null */
    public ?Closure $onUpdateProduct = null;

    /** @var (Closure(int, bool): void)|null throw from here to simulate a failed delete */
    public ?Closure $onDeleteProduct = null;

    public function ping(): void
    {
        $this->calls[] = 'ping';
    }

    public function listProducts(array $query = []): array
    {
        $this->calls[] = 'listProducts';

        return array_values($this->productsBySku);
    }

    public function findProductBySku(string $sku): ?array
    {
        $this->calls[] = 'findProductBySku:'.$sku;

        return $this->productsBySku[$sku] ?? null;
    }

    public function getProduct(int $wooId): array
    {
        $this->calls[] = 'getProduct:'.$wooId;

        if ($this->onGetProduct !== null) {
            return ($this->onGetProduct)($wooId);
        }

        // Fall back to a bare stock-managed product with no quantity, so tests
        // that only exercise create/update routing need not register one.
        return $this->productsById[$wooId]
            ?? ['id' => $wooId, 'manage_stock' => true, 'stock_quantity' => null];
    }

    public function createProduct(array $payload): array
    {
        $this->calls[] = 'createProduct';
        $this->createPayloads[] = $payload;

        if ($this->onCreateProduct !== null) {
            return ($this->onCreateProduct)($payload);
        }

        $product = array_merge(
            ['manage_stock' => false, 'stock_quantity' => null],
            $payload,
            ['id' => $this->nextId++],
        );

        $this->createdProducts[] = $product;
        $this->productsById[$product['id']] = $product;

        if (isset($payload['sku'])) {
            $this->productsBySku[$payload['sku']] = $product;
        }

        return $product;
    }

    public function updateProduct(int $wooId, array $payload): array
    {
        $this->calls[] = 'updateProduct:'.$wooId;
        $this->updatePayloads[$wooId] = $payload;

        if ($this->onUpdateProduct !== null) {
            return ($this->onUpdateProduct)($wooId, $payload);
        }

        $existing = $this->productsById[$wooId]
            ?? ['id' => $wooId, 'manage_stock' => false, 'stock_quantity' => null];

        $updated = array_merge($existing, $payload, ['id' => $wooId]);
        $this->productsById[$wooId] = $updated;

        if (isset($updated['sku'])) {
            $this->productsBySku[$updated['sku']] = $updated;
        }

        return $updated;
    }

    public function deleteProduct(int $wooId, bool $force = false): void
    {
        $this->calls[] = 'deleteProduct:'.$wooId.':'.($force ? 'force' : 'trash');

        if ($this->onDeleteProduct !== null) {
            ($this->onDeleteProduct)($wooId, $force);

            return;
        }

        $this->deletedIds[] = $wooId;
        unset($this->productsById[$wooId]);
    }

    public function listCategories(array $query = []): array
    {
        $this->calls[] = 'listCategories';

        $search = mb_strtolower((string) ($query['search'] ?? ''));

        return array_values(array_filter(
            $this->categories,
            static fn (array $category): bool => $search === ''
                || str_contains(mb_strtolower((string) $category['name']), $search),
        ));
    }

    public function createCategory(array $payload): array
    {
        $this->calls[] = 'createCategory';

        $category = $payload + ['id' => $this->nextId++, 'parent' => $payload['parent'] ?? 0];
        $this->categories[] = $category;
        $this->createdCategories[] = $category;

        return $category;
    }
}
