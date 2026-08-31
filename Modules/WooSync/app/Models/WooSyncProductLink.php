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
 * `images_hash` fingerprints the image set last pushed, so a later update can
 * skip re-sending unchanged images.
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
    ];

    protected function casts(): array
    {
        return [
            'woocommerce_id' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
