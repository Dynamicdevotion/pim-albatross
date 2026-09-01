<?php

namespace Modules\WooSync\Support;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Modules\Products\Models\Product;
use Modules\WooSync\Contracts\WooCommerceClient;
use Modules\WooSync\Exceptions\WooSyncException;
use Modules\WooSync\Models\WooSyncProductLink;
use Modules\WooSync\Providers\WooSyncServiceProvider;

/**
 * Propagates a PIM product deletion to WooCommerce. Hooked onto
 * {@see Product}'s `deleting` event by {@see WooSyncServiceProvider}
 * so that `Modules\Products` never has to know WooSync exists.
 *
 * A `variant` is deleted as a *variation* of its parent
 * (`DELETE /products/{parent}/variations/{id}`) — its own link's
 * `woocommerce_id` is a variation id, meaningless as a top-level product id.
 * Everything else (simple, and a `variable` parent — its variations go away
 * with it on the store side, no separate calls needed) is deleted as a
 * regular product, same as before.
 *
 * The store call always uses `force: false` (trash, not permanent delete) —
 * the more conservative choice for something triggered automatically. A
 * failed call never blocks the PIM deletion: it is logged, and — best effort
 * — surfaced as a Filament notification when the deletion happens inside an
 * interactive request. Either way `woosync_product_links.product_id` is
 * `cascadeOnDelete()`, so the stale link disappears with the product
 * regardless of whether the store call succeeded.
 */
class ProductDeletionSync
{
    public function __construct(private readonly WooCommerceClient $client) {}

    public function handle(Product $product): void
    {
        $link = WooSyncProductLink::query()->where('product_id', $product->id)->first();

        if ($link === null || $link->woocommerce_id === null) {
            return;
        }

        try {
            if ($product->isVariant()) {
                $this->deleteVariant($product, $link);
            } else {
                $this->client->deleteProduct((int) $link->woocommerce_id, force: false);
            }
        } catch (WooSyncException $e) {
            $this->reportFailure($product, $link, $e);
        }
    }

    private function deleteVariant(Product $variant, WooSyncProductLink $link): void
    {
        $parentLink = WooSyncProductLink::query()->where('product_id', $variant->parent_id)->first();

        if ($parentLink === null || $parentLink->woocommerce_id === null) {
            // The parent was never synced (or its link is gone) — there is
            // no Woo variation to clean up.
            return;
        }

        $this->client->deleteVariation(
            (int) $parentLink->woocommerce_id,
            (int) $link->woocommerce_id,
            force: false,
        );
    }

    private function reportFailure(Product $product, WooSyncProductLink $link, WooSyncException $e): void
    {
        Log::error('WooSync: cancellazione non propagata a WooCommerce.', [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'woocommerce_id' => $link->woocommerce_id,
            'error' => $e->getMessage(),
        ]);

        if (request()?->hasSession()) {
            Notification::make()
                ->danger()
                ->title(__('pim.woosync.notify.delete_failed', ['product' => $product->sku ?: '#'.$product->id]))
                ->body($e->getMessage())
                ->send();
        }
    }
}
