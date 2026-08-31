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
 * reconcile stock between the two sides, and append a per-product outcome row
 * to the run's report. Used inline for small runs and from {@see RunWooSync}
 * for large ones.
 *
 * Stock is not a blind overwrite in either direction. Both sides move between
 * syncs — the PIM for production and manual corrections, the store for sales —
 * so the runner keeps a per-product baseline in
 * {@see WooSyncProductLink::$last_known_stock} and reconciles:
 *
 * - **first sync** (no baseline): send the PIM's stock to the store as-is with
 *   `manage_stock: true`, and record it as the baseline.
 * - **later syncs**: read the store's current quantity (authoritative for
 *   sales), add the PIM-side change since the baseline
 *   (`current PIM - last_known`), write the result to both sides, and move the
 *   baseline to it. A result below zero is clamped to zero.
 * - **store stock management off**: never forced back on — stock is left alone
 *   on both sides, the baseline is dropped, and the report says so.
 * - **linked product gone (404)**: treated as a first sync again.
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
            $notes = $payload->warnings;

            $link = WooSyncProductLink::query()->firstOrNew(['product_id' => $product->id]);
            $hadLinkId = $link->woocommerce_id !== null;

            // Resolve the current store product first, so stock can be
            // reconciled against it before we write. A linked id that 404s is
            // cleared here and the sync falls back to a create.
            $storeProduct = $this->currentStoreProduct($product, $link);
            $recreated = $hadLinkId && $storeProduct === null;

            [$stockFields, $pimStock, $stockNote, $nextLastKnown] =
                $this->reconcileStock($product, $link, $storeProduct, $recreated);

            if ($stockNote !== null) {
                $notes[] = $stockNote;
            }

            [$wooProduct, $result] = $this->createOrUpdate($link, array_merge($body, $stockFields), $storeProduct);

            if ($pimStock !== null && (int) ($product->stock ?? 0) !== $pimStock) {
                $product->forceFill(['stock' => $pimStock])->saveQuietly();
            }

            $link->fill([
                'woocommerce_id' => $wooProduct['id'] ?? $link->woocommerce_id,
                'images_hash' => $payload->imagesHash(),
                'last_synced_at' => now(),
                'last_status' => $result,
                'last_known_stock' => $nextLastKnown,
            ])->save();

            return $base + [
                'result' => $result,
                'reason' => $notes === [] ? null : implode(' · ', $notes),
            ];
        } catch (RateLimited $e) {
            throw $e;
        } catch (WooSyncException $e) {
            return $base + ['result' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /**
     * The store product this PIM product currently maps to, or null if there is
     * none yet. A linked `woocommerce_id` that the store reports as gone (404)
     * is cleared, along with the stale stock baseline, so the caller recreates.
     *
     * @return array<string, mixed>|null
     */
    private function currentStoreProduct(Product $product, WooSyncProductLink $link): ?array
    {
        if ($link->woocommerce_id !== null) {
            try {
                return $this->client->getProduct((int) $link->woocommerce_id);
            } catch (ResourceGone) {
                $link->woocommerce_id = null;
                $link->last_known_stock = null;

                return null;
            }
        }

        return $this->client->findProductBySku((string) $product->sku);
    }

    /**
     * Work out the stock fields to merge into the payload, whether to write a
     * value back onto `product.stock`, a human note for the report, and the new
     * `last_known_stock` baseline for the link.
     *
     * @param  array<string, mixed>|null  $storeProduct
     * @return array{0: array<string, mixed>, 1: int|null, 2: string|null, 3: int|null}
     */
    private function reconcileStock(
        Product $product,
        WooSyncProductLink $link,
        ?array $storeProduct,
        bool $recreated,
    ): array {
        $pim = (int) ($product->stock ?? 0);

        // First sync: nothing to reconcile against — push the PIM value as-is
        // and let it become the baseline.
        if ($storeProduct === null) {
            return [
                ['manage_stock' => true, 'stock_quantity' => $pim],
                null,
                __($recreated ? 'pim.woosync.stock.recreated' : 'pim.woosync.stock.first_sync'),
                $pim,
            ];
        }

        // The store has stock management turned off (an operator's choice) —
        // never force it back on. Leave both sides untouched and drop the
        // baseline so a later re-enable is a clean first sync.
        if (($storeProduct['manage_stock'] ?? false) !== true) {
            return [[], null, __('pim.woosync.stock.woo_unmanaged'), null];
        }

        $storeQty = $storeProduct['stock_quantity'] ?? null;
        $store = $storeQty === null ? 0 : (int) $storeQty;

        // The product exists on the store and is stock-managed, but we have no
        // baseline yet: this is its first stock sync, so the PIM value wins
        // (the agreed bootstrap behaviour, even though it overwrites the store).
        if ($link->last_known_stock === null) {
            return [
                ['manage_stock' => true, 'stock_quantity' => $pim],
                null,
                __('pim.woosync.stock.first_sync_overwrite', ['woo' => $store]),
                $pim,
            ];
        }

        // Delta reconciliation: the store's current quantity already reflects
        // sales; add the PIM-side change since the baseline on top of it.
        $delta = $pim - (int) $link->last_known_stock;
        $new = $store + $delta;

        $clamped = $new < 0;
        if ($clamped) {
            $new = 0;
        }

        $note = __('pim.woosync.stock.delta_applied', [
            'delta' => ($delta >= 0 ? '+' : '').$delta,
            'woo' => $store,
            'new' => $new,
        ]);

        if ($clamped) {
            $note .= ' · '.__('pim.woosync.stock.clamped');
        }

        return [
            ['manage_stock' => true, 'stock_quantity' => $new],
            $new,
            $note,
            $new,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>|null  $storeProduct  already-resolved counterpart, if any
     * @return array{0: array<string, mixed>, 1: string} [woo product, 'created'|'updated']
     */
    private function createOrUpdate(WooSyncProductLink $link, array $body, ?array $storeProduct): array
    {
        if ($storeProduct !== null && isset($storeProduct['id'])) {
            try {
                return [$this->client->updateProduct((int) $storeProduct['id'], $body), 'updated'];
            } catch (ResourceGone) {
                // Raced with a deletion between our read and our write — drop
                // the stale id and baseline and recreate.
                $link->woocommerce_id = null;
                $link->last_known_stock = null;
            }
        }

        return [$this->client->createProduct($body), 'created'];
    }
}
