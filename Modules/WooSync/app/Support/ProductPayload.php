<?php

namespace Modules\WooSync\Support;

use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;

/**
 * Builds the WooCommerce product payload for one PIM product and records what,
 * if anything, had to be left out. The v1 field set is fixed: sku, name and
 * description (base language), regular_price (from the default price list),
 * weight and dimensions, images (main image then gallery, by public URL),
 * categories (resolved separately by {@see CategoryResolver} and passed in),
 * and upsell_ids/cross_sell_ids (resolved separately by
 * {@see RelatedProductResolver} and passed in, same shape as categories).
 *
 * Only `simple` products are pushable; `variable` / `variant` rows are the
 * runner's concern (skipped with a reason) and never reach here.
 */
class ProductPayload
{
    /** @var list<string> human-readable notes about data that was omitted */
    public array $warnings = [];

    public function __construct(private readonly Product $product) {}

    public static function for(Product $product): self
    {
        return new self($product);
    }

    /**
     * @param  list<int>  $categoryIds  already-resolved Woo category ids
     * @param  list<int>  $upsellIds  already-resolved Woo product ids
     * @param  list<int>  $crossSellIds  already-resolved Woo product ids
     * @return array<string, mixed>
     */
    public function build(array $categoryIds = [], array $upsellIds = [], array $crossSellIds = []): array
    {
        $translation = $this->product->translate(Locales::baseCode());

        if ($translation?->name === null) {
            $this->warnings[] = __('pim.woosync.warn.no_name', ['locale' => strtoupper(Locales::baseCode())]);
        }

        // No stock fields are ever sent. Inventory is one-directional,
        // WooCommerce -> PIM: the store owns stock and WooSyncRunner writes it
        // back onto the product. Asserting `manage_stock` here would both land
        // every created product as "out of stock" (no quantity accompanies it)
        // and prime the write-back to overwrite the PIM's real stock.
        $payload = [
            'name' => $translation?->name ?? (string) $this->product->sku,
            'type' => 'simple',
            'sku' => (string) $this->product->sku,
            // 'archived' products never reach here (WooSyncRunner skips them
            // before building a payload), so this only ever distinguishes
            // 'active' from 'draft'.
            'status' => $this->product->status === 'active' ? 'publish' : 'draft',
            'description' => (string) ($translation?->description ?? ''),
        ];

        $payload += $this->priceFields();
        $payload += $this->shippingFields();

        $images = $this->imageUrls();
        if ($images !== []) {
            $payload['images'] = array_map(static fn (string $src): array => ['src' => $src], $images);
        }

        if ($categoryIds !== []) {
            $payload['categories'] = array_map(static fn (int $id): array => ['id' => $id], $categoryIds);
        }

        if ($upsellIds !== []) {
            $payload['upsell_ids'] = $upsellIds;
        }

        if ($crossSellIds !== []) {
            $payload['cross_sell_ids'] = $crossSellIds;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function priceFields(): array
    {
        // A variable product never carries its own price — its variants do —
        // so there is nothing missing to warn about here.
        if ($this->product->isVariable()) {
            return [];
        }

        $list = PriceList::default();

        $price = $list !== null
            ? $this->product->prices->firstWhere('price_list_id', $list->id)?->price
            : null;

        if ($price === null) {
            $this->warnings[] = __('pim.woosync.warn.no_price');

            return [];
        }

        return ['regular_price' => (string) $price];
    }

    /** @return array<string, mixed> */
    private function shippingFields(): array
    {
        $fields = [];

        if ($this->product->weight !== null) {
            $fields['weight'] = (string) $this->product->weight;
        }

        $dimensions = array_filter([
            'length' => $this->product->length,
            'width' => $this->product->width,
            'height' => $this->product->height,
        ], static fn ($value): bool => $value !== null);

        if ($dimensions !== []) {
            $fields['dimensions'] = array_map(static fn ($value): string => (string) $value, $dimensions);
        }

        return $fields;
    }

    /**
     * Public URLs of the product's images: main image first, then the gallery,
     * in order. WooCommerce downloads each by URL, so the media disk must be
     * publicly reachable — it is (`public` disk, HTTPS APP_URL).
     *
     * @return list<string>
     */
    public function imageUrls(): array
    {
        $urls = [];

        $main = $this->product->getFirstMediaUrl('main_image');
        if ($main !== '') {
            $urls[] = $main;
        }

        foreach ($this->product->getMedia('gallery') as $media) {
            $urls[] = $media->getUrl();
        }

        return array_values(array_unique($urls));
    }

    public function imagesHash(): string
    {
        return md5(implode('|', $this->imageUrls()));
    }
}
