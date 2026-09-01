<?php

namespace Modules\WooSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Products\Models\Product;

/**
 * The link between a PIM product and its counterpart in the WooCommerce store.
 * Kept in its own table rather than as a `woocommerce_id` column on `products`
 * so the module stays fully removable — dropping WooSync drops this table and
 * leaves `products` untouched.
 *
 * One row per `Product` row regardless of type — `product_id` is unique here
 * exactly as it is unique in `products`. What `woocommerce_id` *means*
 * depends on the linked product's type: for a `simple` product or a
 * `variable` parent it is a top-level Woo product id; for a `variant` it is
 * the id of a Woo *variation*, scoped under its parent's own
 * `woocommerce_id` (`/products/{parent}/variations/{this}`) — never a
 * product id on its own. Callers always know which is which from
 * `$product->type`, so no extra column distinguishes them.
 *
 * `images_hash` fingerprints the image set last pushed, so a later update can
 * skip re-sending unchanged images.
 *
 * `last_known_stock` is the reconciled stock value last written to both the
 * store and `product.stock`; it is the baseline the runner's delta model
 * measures the next PIM-side change against. Null means no stock sync has
 * happened yet (or the store has stock management turned off), so the next one
 * is treated as a first sync.
 */
class WooSyncProductLink extends Model
{
    protected $table = 'woosync_product_links';

    protected $fillable = [
        'product_id',
        'woocommerce_id',
        'images_hash',
        'last_synced_at',
        'last_status',
        'last_known_stock',
    ];

    protected function casts(): array
    {
        return [
            'woocommerce_id' => 'integer',
            'last_known_stock' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
