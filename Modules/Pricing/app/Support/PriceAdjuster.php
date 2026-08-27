<?php

namespace Modules\Pricing\Support;

use Illuminate\Database\Eloquent\Builder;
use Modules\Pricing\Models\ProductPrice;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * Bulk price operations for the price grid. Every method works on a single
 * price list and only touches product_prices rows that already exist — a
 * product with no price in that list is skipped.
 */
class PriceAdjuster
{
    /**
     * Set the same price on a set of products (creating the row if missing).
     *
     * @param  iterable<int>  $productIds
     * @return int  number of rows written
     */
    public static function setFixed(iterable $productIds, int $priceListId, float $price): int
    {
        $price = round($price, 2);
        $count = 0;

        foreach ($productIds as $productId) {
            ProductPrice::updateOrCreate(
                ['product_id' => $productId, 'price_list_id' => $priceListId],
                ['price' => $price],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Apply a signed percentage change to the given products' price in one list.
     *
     * @param  iterable<int>  $productIds
     * @return int  number of rows changed
     */
    public static function adjustProducts(iterable $productIds, int $priceListId, float $percent): int
    {
        return static::apply(
            ProductPrice::query()
                ->where('price_list_id', $priceListId)
                ->whereIn('product_id', collect($productIds)->all()),
            $percent,
        );
    }

    /**
     * Apply a signed percentage change to every product in a taxonomy term
     * (and its descendants), in one list only.
     *
     * @return int  number of rows changed
     */
    public static function adjustCategory(int $taxonomyTermId, int $priceListId, float $percent): int
    {
        $term = TaxonomyTerm::query()->find($taxonomyTermId);

        if ($term === null) {
            return 0;
        }

        $termIds = [$term->getKey(), ...$term->descendantIds()];

        $productIds = Product::query()
            ->whereHas('taxonomyTerms', fn (Builder $query) => $query->whereIn('taxonomy_terms.id', $termIds))
            ->pluck('id');

        return static::apply(
            ProductPrice::query()
                ->where('price_list_id', $priceListId)
                ->whereIn('product_id', $productIds),
            $percent,
        );
    }

    /**
     * @param  Builder<ProductPrice>  $query
     */
    protected static function apply(Builder $query, float $percent): int
    {
        $factor = 1 + ($percent / 100);
        $count = 0;

        foreach ($query->get() as $row) {
            $row->update(['price' => round((float) $row->price * $factor, 2)]);
            $count++;
        }

        return $count;
    }
}
