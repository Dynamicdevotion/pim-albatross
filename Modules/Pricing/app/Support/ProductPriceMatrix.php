<?php

namespace Modules\Pricing\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;

/**
 * Reads and writes one product's price in every price list as a fixed grid —
 * one row per active list. Backs the per-list price table on the product and
 * variant forms; the same "blank means no row" rule as the global price grid
 * (@see \Modules\Pricing\Filament\Pages\ManagePrices::writePrice()).
 */
class ProductPriceMatrix
{
    /**
     * Active price lists, default first then by name.
     *
     * @return Collection<int, PriceList>
     */
    public static function activeLists(): Collection
    {
        return PriceList::query()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    /**
     * The fixed-row editor state: one row per active price list, carrying the
     * product's current price (and sale price) in that list or null when it
     * has none. Passing no product (or an unsaved one) yields every row
     * empty — the create-form seed.
     *
     * @return list<array{price_list_id: int, price_list_label: string, price: string|null, sale_price: string|null}>
     */
    public static function readItems(?Product $product = null): array
    {
        $prices = ($product && $product->exists)
            ? ($product->relationLoaded('prices') ? $product->prices : $product->prices()->get())
            : collect();

        return static::activeLists()
            ->map(function (PriceList $list) use ($prices): array {
                $price = $prices->firstWhere('price_list_id', $list->id);

                return [
                    'price_list_id' => $list->id,
                    'price_list_label' => $list->name,
                    'price' => $price?->price,
                    'sale_price' => $price?->sale_price,
                ];
            })
            ->all();
    }

    /**
     * Persist the editor rows for one product. A non-empty price is upserted
     * along with whatever sale price came with it (blank -> null, a plain
     * discount price — see the `sale_price` column's docblock for the
     * WooCommerce mapping this exists for); an empty price removes any
     * existing row for that (product, list) entirely, sale price included —
     * clearing a field deletes the price rather than storing a null. Rows for
     * lists that are not currently active are ignored.
     *
     * @param  iterable<array{price_list_id?: int|string|null, price?: mixed, sale_price?: mixed}>  $rows
     */
    public static function write(Product $product, iterable $rows): void
    {
        $activeIds = static::activeLists()->pluck('id')->all();

        DB::transaction(function () use ($product, $rows, $activeIds): void {
            foreach ($rows as $row) {
                $listId = (int) ($row['price_list_id'] ?? 0);

                if (! in_array($listId, $activeIds, true)) {
                    continue;
                }

                $raw = $row['price'] ?? null;
                $price = ($raw === '' || $raw === null) ? null : round((float) $raw, 2);

                if ($price === null) {
                    $product->prices()->where('price_list_id', $listId)->delete();

                    continue;
                }

                $rawSale = $row['sale_price'] ?? null;
                $salePrice = ($rawSale === '' || $rawSale === null) ? null : round((float) $rawSale, 2);

                $product->prices()->updateOrCreate(
                    ['price_list_id' => $listId],
                    ['price' => $price, 'sale_price' => $salePrice],
                );
            }
        });
    }
}
