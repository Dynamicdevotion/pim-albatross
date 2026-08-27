<?php

namespace Modules\Products\Filament\Resources\Products\Concerns;

use Modules\Pricing\Support\ProductPriceMatrix;

/**
 * Shared per-list price load/save for the Create and Edit product pages.
 *
 * The form keeps the price grid under the non-column `prices` key (one row per
 * active price list); these helpers move it in and out of product_prices around
 * the record save, via ProductPriceMatrix. Mirrors HandlesProductTranslations.
 */
trait HandlesProductPrices
{
    /**
     * Price rows pulled out of the record payload.
     *
     * @var array<int, array{price_list_id?: mixed, price?: mixed}>
     */
    protected array $priceRows = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractPrices(array $data): array
    {
        $this->priceRows = array_values($data['prices'] ?? []);
        unset($data['prices']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillPrices(array $data): array
    {
        $data['prices'] = ProductPriceMatrix::readItems($this->record);

        return $data;
    }

    protected function savePrices(): void
    {
        ProductPriceMatrix::write($this->record, $this->priceRows);
    }
}
