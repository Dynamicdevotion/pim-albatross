<?php

namespace Modules\Pricing\Filament\Resources\PriceLists\Concerns;

use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Models\ProductPrice;

/**
 * "Populate prices from another list" on price-list creation: copies every
 * product_prices row from a source list, applying an optional percentage change.
 */
trait HandlesPricePopulation
{
    protected ?int $sourcePriceListId = null;

    protected float $adjustmentPercent = 0.0;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractPopulation(array $data): array
    {
        $source = $data['source_price_list_id'] ?? null;
        $this->sourcePriceListId = filled($source) ? (int) $source : null;

        $percent = $data['adjustment_percent'] ?? null;
        $this->adjustmentPercent = filled($percent) ? (float) $percent : 0.0;

        unset($data['source_price_list_id'], $data['adjustment_percent']);

        return $data;
    }

    protected function populatePrices(PriceList $target): void
    {
        if ($this->sourcePriceListId === null) {
            return;
        }

        $factor = 1 + ($this->adjustmentPercent / 100);

        ProductPrice::query()
            ->where('price_list_id', $this->sourcePriceListId)
            ->get()
            ->each(fn (ProductPrice $row) => $target->prices()->updateOrCreate(
                ['product_id' => $row->product_id],
                ['price' => round((float) $row->price * $factor, 2)],
            ));
    }
}
