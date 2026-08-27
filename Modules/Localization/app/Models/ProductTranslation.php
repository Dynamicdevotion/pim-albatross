<?php

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Localization\Database\Factories\ProductTranslationFactory;
use Modules\Products\Models\Product;

/**
 * A single translation of a product's content into one locale.
 *
 * One row per (product_id, locale). There is no automatic fallback: a missing
 * row means the content is simply not available in that language.
 */
class ProductTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'locale',
        'name',
        'description',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory(): ProductTranslationFactory
    {
        return ProductTranslationFactory::new();
    }
}
