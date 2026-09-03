<?php

namespace Modules\Products\Filament\Resources\Products\Concerns;

use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;

/**
 * Shared up-sell / cross-sell load/save for the Create and Edit product
 * pages. The form keeps the two picks under the non-column `upsell_ids` /
 * `cross_sell_ids` keys (plain id arrays — not a Filament `relationship()`
 * select, so the picker never has to preload the whole catalogue; see
 * {@see \Modules\Products\Support\RelatedProductPicker}); these helpers move
 * them in and out of the `product_upsells` / `product_cross_sells` pivots
 * around the record save. Mirrors {@see HandlesProductPrices}.
 */
trait HandlesProductRelations
{
    protected array $upsellIds = [];

    protected array $crossSellIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractRelations(array $data): array
    {
        $this->upsellIds = $data['upsell_ids'] ?? [];
        $this->crossSellIds = $data['cross_sell_ids'] ?? [];
        unset($data['upsell_ids'], $data['cross_sell_ids']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillRelations(array $data): array
    {
        $data['upsell_ids'] = $this->record->upsells()->pluck('products.id')->all();
        $data['cross_sell_ids'] = $this->record->crossSells()->pluck('products.id')->all();

        return $data;
    }

    /**
     * Syncs both pivots. The form's search already excludes the record
     * itself and any `variant`, but this is the actual save boundary, so it
     * re-checks both rather than trusting form state alone.
     */
    protected function saveRelations(): void
    {
        $this->record->upsells()->sync($this->validRelatedIds($this->upsellIds));
        $this->record->crossSells()->sync($this->validRelatedIds($this->crossSellIds));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    protected function validRelatedIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $ids = array_diff($ids, [$this->record->id]);

        if ($ids === []) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->where('type', '!=', ProductType::Variant->value)
            ->pluck('id')
            ->all();
    }
}
