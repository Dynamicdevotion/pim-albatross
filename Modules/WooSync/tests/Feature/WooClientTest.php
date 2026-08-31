<?php

namespace Modules\WooSync\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\WooSync\Exceptions\AuthenticationFailed;
use Modules\WooSync\Exceptions\RateLimited;
use Modules\WooSync\Exceptions\RequestRejected;
use Modules\WooSync\Exceptions\ResourceGone;
use Modules\WooSync\Exceptions\StoreError;
use Modules\WooSync\Exceptions\StoreUnreachable;
use Modules\WooSync\Models\WooSyncSetting;
use Modules\WooSync\Support\Http\BasicAuthWooClient;
use Tests\TestCase;

class WooClientTest extends TestCase
{
    use RefreshDatabase;

    private function client(): BasicAuthWooClient
    {
        $settings = WooSyncSetting::current();
        $settings->update([
            'store_url' => 'https://shop.example.com',
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ]);

        return new BasicAuthWooClient($settings);
    }

    public function test_it_returns_decoded_json_on_success(): void
    {
        Http::fake(['*' => Http::response([['id' => 1, 'sku' => 'A']], 200)]);

        $this->assertSame([['id' => 1, 'sku' => 'A']], $this->client()->listProducts());
    }

    public function test_it_calls_the_v3_rest_endpoint_with_basic_auth(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $this->client()->ping();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://shop.example.com/wp-json/wc/v3/system_status'
            && $request->hasHeader('Authorization'));
    }

    public function test_401_maps_to_authentication_failed(): void
    {
        Http::fake(['*' => Http::response(['message' => 'invalid key'], 401)]);

        $this->expectException(AuthenticationFailed::class);

        $this->client()->listProducts();
    }

    public function test_404_maps_to_resource_gone(): void
    {
        Http::fake(['*' => Http::response(['message' => 'not found'], 404)]);

        $this->expectException(ResourceGone::class);

        $this->client()->updateProduct(9, []);
    }

    public function test_429_maps_to_rate_limited_and_reads_retry_after(): void
    {
        Http::fake(['*' => Http::response('', 429, ['Retry-After' => '30'])]);

        try {
            $this->client()->createProduct([]);
            $this->fail('expected RateLimited');
        } catch (RateLimited $e) {
            $this->assertSame(30, $e->retryAfter);
        }
    }

    public function test_422_maps_to_request_rejected_carrying_the_store_message(): void
    {
        Http::fake(['*' => Http::response(['message' => 'SKU already exists'], 422)]);

        try {
            $this->client()->createProduct(['sku' => 'DUP']);
            $this->fail('expected RequestRejected');
        } catch (RequestRejected $e) {
            $this->assertStringContainsString('SKU already exists', $e->getMessage());
        }
    }

    public function test_500_maps_to_store_error(): void
    {
        Http::fake(['*' => Http::response('server exploded', 500)]);

        $this->expectException(StoreError::class);

        $this->client()->listProducts();
    }

    public function test_a_connection_failure_maps_to_store_unreachable(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('could not resolve host');
        });

        $this->expectException(StoreUnreachable::class);

        $this->client()->ping();
    }

    public function test_an_unconfigured_connection_raises_authentication_failed(): void
    {
        $this->expectException(AuthenticationFailed::class);

        (new BasicAuthWooClient(new WooSyncSetting))->ping();
    }
}
