<?php

namespace Modules\Products\Filament\Resources\Products\Concerns;

use Modules\Pricing\Support\ProductPriceMatrix;
use Modules\Products\Models\Product;

/**
 * Per-list price load/save for the variant Create/Edit actions in the
 * VariantsRelationManager. Mirrors HandlesProductPrices (used by the product
 * pages) but works on a record passed into an action closure.
 */
trait HandlesVariantPrices
{
    /** @var array<int, array{price_list_id?: mixed, price?: mixed}> */
    protected array $variantPriceRows = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullVariantPrices(array $data): array
    {
        $this->variantPriceRows = array_values($data['prices'] ?? []);
        unset($data['prices']);

        return $data;
    }

    /**
     * @return list<array{price_list_id: int, price_list_label: string, price: string|null}>
     */
    protected function readVariantPrices(Product $record): array
    {
        return ProductPriceMatrix::readItems($record);
    }

    protected function saveVariantPrices(Product $record): void
    {
        ProductPriceMatrix::write($record, $this->variantPriceRows);
    }
}
