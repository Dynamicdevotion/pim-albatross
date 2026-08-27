<?php

namespace Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Pricing\Database\Factories\ProductPriceFactory;
use Modules\Products\Models\Product;

/**
 * The price of one product in one price list. One row per
 * (product_id, price_list_id).
 */
class ProductPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'price_list_id',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    protected static function newFactory(): ProductPriceFactory
    {
        return ProductPriceFactory::new();
    }
}
