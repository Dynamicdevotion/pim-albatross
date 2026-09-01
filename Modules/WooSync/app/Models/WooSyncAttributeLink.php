<?php

namespace Modules\WooSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Taxonomies\Models\Taxonomy;

/**
 * Maps a PIM taxonomy used to generate product variants to the id of the
 * matching WooCommerce global product attribute (`pa_*`), so a sync resolves
 * each taxonomy to its Woo attribute once and then reuses the id on later
 * runs.
 */
class WooSyncAttributeLink extends Model
{
    protected $table = 'woosync_attribute_links';

    protected $fillable = [
        'taxonomy_id',
        'woocommerce_attribute_id',
    ];

    protected function casts(): array
    {
        return [
            'woocommerce_attribute_id' => 'integer',
        ];
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }
}
