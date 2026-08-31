<?php

namespace Modules\WooSync\Support\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\WooSync\Contracts\WooCommerceClient;
use Modules\WooSync\Exceptions\WooSyncException;
use Modules\WooSync\Models\WooSyncSetting;

/**
 * WooCommerce REST v3 client using HTTP Basic Auth with the consumer key /
 * secret — WooCommerce's standard scheme when the store is served over HTTPS,
 * which WooSync requires. A plain-HTTP store would need OAuth 1.0a request
 * signing instead; that is deliberately not implemented (see the module
 * README) — the connection page refuses a non-HTTPS URL.
 *
 * Every non-2xx response and every transport failure is turned into a
 * {@see WooSyncException} subclass carrying a translated, user-facing reason.
 */
class BasicAuthWooClient implements WooCommerceClient
{
    public function __construct(private readonly WooSyncSetting $settings) {}

    public function ping(): void
    {
        $this->request('get', 'system_status');
    }

    public function listProducts(array $query = []): array
    {
        return $this->request('get', 'products', $query)->json() ?? [];
    }

    public function findProductBySku(string $sku): ?array
    {
        if (trim($sku) === '') {
            return null;
        }

        $matches = $this->request('get', 'products', ['sku' => $sku])->json() ?? [];

        return $matches[0] ?? null;
    }

    public function createProduct(array $payload): array
    {
        return $this->request('post', 'products', $payload)->json() ?? [];
    }

    public function updateProduct(int $wooId, array $payload): array
    {
        return $this->request('put', "products/{$wooId}", $payload)->json() ?? [];
    }

    public function listCategories(array $query = []): array
    {
        return $this->request('get', 'products/categories', $query)->json() ?? [];
    }

    public function createCategory(array $payload): array
    {
        return $this->request('post', 'products/categories', $payload)->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $data  query string for GET, JSON body otherwise
     *
     * @throws WooSyncException
     */
    private function request(string $method, string $path, array $data = []): Response
    {
        if (! $this->settings->isConfigured()) {
            throw WooSyncException::auth(__('pim.woosync.error.not_configured'));
        }

        try {
            $response = $this->client()->{$method}($this->url($path), $data);
        } catch (ConnectionException $e) {
            throw WooSyncException::unreachable($e->getMessage());
        }

        return $this->guard($response);
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->settings->consumer_key, $this->settings->consumer_secret)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('woosync.timeout', 20))
            ->connectTimeout(10)
            ->retry(2, 400, throw: false);
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->settings->store_url, '/').'/wp-json/wc/v3/'.ltrim($path, '/');
    }

    /**
     * @throws WooSyncException
     */
    private function guard(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $body = $response->json();
        $detail = is_array($body) ? ($body['message'] ?? null) : null;
        $status = $response->status();

        throw match (true) {
            $status === 401, $status === 403 => WooSyncException::auth($detail),
            $status === 404 => WooSyncException::gone($detail),
            $status === 429 => WooSyncException::rateLimited(
                ((int) $response->header('Retry-After')) ?: null,
            ),
            $status >= 500 => WooSyncException::storeError($detail),
            default => WooSyncException::rejected($detail ?? ('HTTP '.$status)),
        };
    }
}
