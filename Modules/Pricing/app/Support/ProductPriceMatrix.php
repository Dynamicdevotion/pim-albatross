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
     * product's current price in that list or null when it has none.
     *
     * @return list<array{price_list_id: int, price_list_label: string, price: string|null}>
     */
    public static function readItems(Product $product): array
    {
        $prices = $product->relationLoaded('prices')
            ? $product->prices
            : $product->prices()->get();

        return static::activeLists()
            ->map(fn (PriceList $list): array => [
                'price_list_id' => $list->id,
                'price_list_label' => $list->name,
                'price' => $prices->firstWhere('price_list_id', $list->id)?->price,
            ])
            ->all();
    }

    /**
     * Persist the editor rows for one product. A non-empty price is upserted;
     * an empty one removes any existing row for that (product, list) — so
     * clearing a field deletes the price rather than storing a null. Rows for
     * lists that are not currently active are ignored.
     *
     * @param  iterable<array{price_list_id?: int|string|null, price?: mixed}>  $rows
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

                $product->prices()->updateOrCreate(
                    ['price_list_id' => $listId],
                    ['price' => $price],
                );
            }
        });
    }
}
