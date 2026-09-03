<?php

namespace Modules\WooSync\Support;

use Illuminate\Database\Eloquent\Collection;
use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;
use Modules\WooSync\Models\WooSyncProductLink;

/**
 * Resolves a product's up-sell / cross-sell picks to the WooCommerce product
 * ids {@see ProductPayload} sends — a pure lookup against the already-stored
 * {@see WooSyncProductLink} rows, never a store call of its own (unlike
 * {@see CategoryResolver}, which can create on the store; a related product
 * either has been synced before, giving it a `woocommerce_id`, or it hasn't).
 *
 * A related product with no link yet, or a link with no `woocommerce_id`,
 * is left out of the id list rather than failing the sync — noted instead,
 * so the main product still goes through.
 */
class RelatedProductResolver
{
    /**
     * @param  Collection<int, Product>  $related
     * @return array{0: list<int>, 1: list<string>} [Woo ids, warning notes]
     */
    public static function resolve(Collection $related, string $type): array
    {
        if ($related->isEmpty()) {
            return [[], []];
        }

        $links = WooSyncProductLink::query()
            ->whereIn('product_id', $related->pluck('id'))
            ->get()
            ->keyBy('product_id');

        $ids = [];
        $notes = [];

        foreach ($related as $product) {
            $wooId = $links->get($product->id)?->woocommerce_id;

            if ($wooId === null) {
                $notes[] = __('pim.woosync.warn.related_not_synced', [
                    'product' => $product->translate(Locales::baseCode())?->name ?? $product->sku,
                    'type' => $type,
                ]);

                continue;
            }

            $ids[] = (int) $wooId;
        }

        return [$ids, $notes];
    }
}
