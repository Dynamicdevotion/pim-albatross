<?php

namespace Modules\WooSync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * Maps a PIM taxonomy term in the "Categorie" taxonomy to the id of the
 * matching native WooCommerce product category, so a sync resolves each term
 * to its Woo category once and then reuses the id on later runs.
 */
class WooSyncCategoryLink extends Model
{
    protected $table = 'woosync_category_links';

    protected $fillable = [
        'taxonomy_term_id',
        'woocommerce_category_id',
    ];

    protected function casts(): array
    {
        return [
            'woocommerce_category_id' => 'integer',
        ];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'taxonomy_term_id');
    }
}
