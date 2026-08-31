<?php

namespace Modules\WooSync\Support;

use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;
use Modules\WooSync\Contracts\WooCommerceClient;
use Modules\WooSync\Exceptions\RateLimited;
use Modules\WooSync\Exceptions\ResourceGone;
use Modules\WooSync\Exceptions\WooSyncException;
use Modules\WooSync\Jobs\RunWooSync;
use Modules\WooSync\Models\WooSyncProductLink;
use Modules\WooSync\Models\WooSyncRun;
use Throwable;

/**
 * Runs one {@see WooSyncRun}: for each product id, push it to WooCommerce
 * (create or update — de-duplicated first by the stored link, then by SKU),
 * write the store's stock value back onto the PIM product, and append a
 * per-product outcome row to the run's report. Used inline for small runs and
 * from {@see RunWooSync} for large ones.
 *
 * A {@see RateLimited} anywhere in the loop stops the whole run: the store is
 * asking us to back off, so what completed so far is saved and the rest is
 * left to a later run.
 */
class WooSyncRunner
{
    public function __construct(private readonly WooCommerceClient $client) {}

    public function run(WooSyncRun $run): void
    {
        $run->update(['status' => 'processing', 'started_at' => now()]);

        $items = [];
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $delayMs = (int) config('woosync.request_delay_ms', 250);

        try {
            $products = Product::query()
                ->whereIn('id', $run->product_ids ?? [])
                ->with(['translations', 'prices', 'media', 'taxonomyTerms.translations'])
                ->get();

            $resolver = new CategoryResolver($this->client);
            $first = true;

            foreach ($products as $product) {
                if (! $first && $delayMs > 0) {
                    usleep($delayMs * 1000);
                }
                $first = false;

                $items[] = $outcome = $this->syncOne($product, $resolver);
                $counts[$outcome['result']]++;
            }

            $run->update([
                'status' => 'completed',
                'items' => $items,
                'created_count' => $counts['created'],
                'updated_count' => $counts['updated'],
                'skipped_count' => $counts['skipped'],
                'failed_count' => $counts['failed'],
                'finished_at' => now(),
            ]);
        } catch (RateLimited $e) {
            $run->update([
                'status' => 'failed',
                'items' => $items,
                'created_count' => $counts['created'],
                'updated_count' => $counts['updated'],
                'skipped_count' => $counts['skipped'],
                'failed_count' => $counts['failed'],
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            if (! $e instanceof WooSyncException) {
                report($e);
            }

            $run->update([
                'status' => 'failed',
                'items' => $items,
                'error_message' => $e instanceof WooSyncException
                    ? $e->getMessage()
                    : __('pim.woosync.error.unexpected'),
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * @return array{product: string, sku: string, result: string, reason: string|null}
     */
    private function syncOne(Product $product, CategoryResolver $resolver): array
    {
        $label = $product->translate(Locales::baseCode())?->name
            ?? $product->sku
            ?? ('#'.$product->id);

        $base = ['product' => (string) $label, 'sku' => (string) $product->sku];

        if (! $product->isSimple()) {
            return $base + ['result' => 'skipped', 'reason' => __('pim.woosync.skip.not_simple')];
        }

        if (blank($product->sku)) {
            return $base + ['result' => 'skipped', 'reason' => __('pim.woosync.skip.no_sku')];
        }

        try {
            $categoryIds = $resolver->idsFor(...CategoryResolver::categoryTerms($product));

            $payload = ProductPayload::for($product);
            $body = $payload->build($categoryIds);

            $link = WooSyncProductLink::query()->firstOrNew(['product_id' => $product->id]);
            [$wooProduct, $result] = $this->createOrUpdate($link, $body);

            $link->fill([
                'woocommerce_id' => $wooProduct['id'] ?? $link->woocommerce_id,
                'images_hash' => $payload->imagesHash(),
                'last_synced_at' => now(),
                'last_status' => $result,
            ])->save();

            $this->writeBackStock($product, $wooProduct);

            return $base + [
                'result' => $result,
                'reason' => $payload->warnings === [] ? null : implode(' · ', $payload->warnings),
            ];
        } catch (RateLimited $e) {
            throw $e;
        } catch (WooSyncException $e) {
            return $base + ['result' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: string} [woo product, 'created'|'updated']
     */
    private function createOrUpdate(WooSyncProductLink $link, array $body): array
    {
        if ($link->woocommerce_id !== null) {
            try {
                return [$this->client->updateProduct($link->woocommerce_id, $body), 'updated'];
            } catch (ResourceGone) {
                // Deleted on the Woo side — drop the stale id and recreate.
                $link->woocommerce_id = null;
            }
        }

        $existing = $this->client->findProductBySku((string) ($body['sku'] ?? ''));

        if ($existing !== null && isset($existing['id'])) {
            return [$this->client->updateProduct((int) $existing['id'], $body), 'updated'];
        }

        return [$this->client->createProduct($body), 'created'];
    }

    /**
     * @param  array<string, mixed>  $wooProduct
     */
    private function writeBackStock(Product $product, array $wooProduct): void
    {
        if (($wooProduct['manage_stock'] ?? false) !== true) {
            return;
        }

        if (! array_key_exists('stock_quantity', $wooProduct) || $wooProduct['stock_quantity'] === null) {
            return;
        }

        $product->forceFill(['stock' => (int) $wooProduct['stock_quantity']])->saveQuietly();
    }
}
