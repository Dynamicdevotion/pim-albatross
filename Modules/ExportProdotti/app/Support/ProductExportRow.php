<?php

namespace Modules\ExportProdotti\Support;

use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;

/**
 * Turns one {@see Product} — a simple product, a variable container or one of
 * its variants — into the ordered list of cell values for the chosen export
 * columns.
 *
 * "Own value, then parent" is applied to a variant's name and images, the same
 * inheritance rule the product form and {@see Product::getMainImageUrl()} use;
 * everything else (sku, price, stock, dimensions) is the row's own.
 */
final class ProductExportRow
{
    public function __construct(
        private readonly int $baseLanguageId,
        private readonly int $defaultPriceListId,
    ) {}

    public static function make(): self
    {
        return new self(
            (int) Locales::base()->id,
            (int) (PriceList::default()?->id ?? 0),
        );
    }

    /**
     * @param  list<string>  $columns  column keys, already ordered
     * @param  ?Product  $parent  the variable container when $product is a variant,
     *                            passed in so no lazy `parent` query is needed
     * @return list<string|null>
     */
    public function values(Product $product, array $columns, ?Product $parent = null): array
    {
        return array_map(fn (string $column): ?string => $this->value($product, $column, $parent), $columns);
    }

    private function value(Product $product, string $column, ?Product $parent): ?string
    {
        return match ($column) {
            'sku' => $product->sku,
            'name' => $this->name($product, $parent),
            'description' => $this->translation($product)?->description,
            'price' => $this->price($product),
            'stock' => $this->nullableString($product->stock),
            'weight' => $this->nullableString($product->weight),
            'length' => $this->nullableString($product->length),
            'width' => $this->nullableString($product->width),
            'height' => $this->nullableString($product->height),
            'status' => $product->status,
            'image_url' => $product->getMainImageUrl() ?? '',
            'gallery_urls' => $this->gallery($product),
            default => null,
        };
    }

    private function name(Product $product, ?Product $parent): ?string
    {
        return $this->translation($product)?->name
            ?? ($parent !== null ? $parent->translate(Locales::codeFor($this->baseLanguageId) ?? '')?->name : null);
    }

    private function translation(Product $product)
    {
        return $product->relationLoaded('translations')
            ? $product->translations->firstWhere('language_id', $this->baseLanguageId)
            : $product->translations()->where('language_id', $this->baseLanguageId)->first();
    }

    private function price(Product $product): ?string
    {
        if ($this->defaultPriceListId === 0) {
            return null;
        }

        $price = $product->relationLoaded('prices')
            ? $product->prices->firstWhere('price_list_id', $this->defaultPriceListId)?->price
            : $product->prices()->where('price_list_id', $this->defaultPriceListId)->value('price');

        return $this->nullableString($price);
    }

    private function gallery(Product $product): string
    {
        return $product->getMedia('gallery')
            ->map(fn ($media): string => $media->getUrl())
            ->implode('|');
    }

    private function nullableString(int|string|float|null $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
