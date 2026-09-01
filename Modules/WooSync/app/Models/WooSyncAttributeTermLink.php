<?php

namespace Modules\WooSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * Maps a PIM taxonomy term used on a product variant to the id of the
 * matching term inside its WooCommerce global attribute. `woocommerce_attribute_id`
 * is kept alongside `woocommerce_term_id` because Woo attribute terms are
 * scoped per attribute (`/products/attributes/{attribute_id}/terms/{term_id}`).
 */
class WooSyncAttributeTermLink extends Model
{
    protected $table = 'woosync_attribute_term_links';

    protected $fillable = [
        'taxonomy_term_id',
        'woocommerce_attribute_id',
        'woocommerce_term_id',
    ];

    protected function casts(): array
    {
        return [
            'woocommerce_attribute_id' => 'integer',
            'woocommerce_term_id' => 'integer',
        ];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'taxonomy_term_id');
    }
}
