<?php

namespace Modules\WooSync\Tests\Support;

use Closure;
use Modules\WooSync\Contracts\WooCommerceClient;

/**
 * In-memory {@see WooCommerceClient} for the runner / action tests: records
 * every call, serves canned data, and lets a test inject failures through the
 * `onCreateProduct` / `onUpdateProduct` hooks.
 */
class FakeWooClient implements WooCommerceClient
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<array<string, mixed>> */
    public array $createdProducts = [];

    /** @var list<array<string, mixed>> */
    public array $createdCategories = [];

    /** @var array<string, array<string, mixed>> keyed by SKU */
    public array $productsBySku = [];

    /** @var list<array<string, mixed>> */
    public array $categories = [];

    public int $nextId = 100;

    /** @var (Closure(array<string, mixed>): array<string, mixed>)|null */
    public ?Closure $onCreateProduct = null;

    /** @var (Closure(int, array<string, mixed>): array<string, mixed>)|null */
    public ?Closure $onUpdateProduct = null;

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

    public function createProduct(array $payload): array
    {
        $this->calls[] = 'createProduct';

        if ($this->onCreateProduct !== null) {
            return ($this->onCreateProduct)($payload);
        }

        $product = $payload + ['id' => $this->nextId++, 'manage_stock' => true, 'stock_quantity' => 7];
        $this->createdProducts[] = $product;

        if (isset($payload['sku'])) {
            $this->productsBySku[$payload['sku']] = $product;
        }

        return $product;
    }

    public function updateProduct(int $wooId, array $payload): array
    {
        $this->calls[] = 'updateProduct:'.$wooId;

        if ($this->onUpdateProduct !== null) {
            return ($this->onUpdateProduct)($wooId, $payload);
        }

        return $payload + ['id' => $wooId, 'manage_stock' => true, 'stock_quantity' => 12];
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
