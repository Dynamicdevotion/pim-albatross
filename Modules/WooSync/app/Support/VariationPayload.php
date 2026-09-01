<?php

namespace Modules\WooSync\Support;

use Modules\Pricing\Models\PriceList;
use Modules\Products\Models\Product;

/**
 * Builds the WooCommerce *variation* payload for one PIM variant: sku,
 * price, its own image — or the parent's, via
 * {@see Product::getMainImageUrl()}'s existing fallback — and the attribute
 * combination that identifies it (resolved by {@see AttributeResolver} and
 * passed in).
 *
 * Stock is included only when `$includeStock` is true (creation only, driven
 * by the caller). Unlike a simple product's stock, a variation has no
 * delta-reconciliation model yet — see the class docs on
 * {@see WooSyncRunner} — so later syncs never touch it; this is a known,
 * documented limit of this first pass at variable-product support.
 */
class VariationPayload
{
    /** @var list<string> */
    public array $warnings = [];

    public function __construct(private readonly Product $variant) {}

    public static function for(Product $variant): self
    {
        return new self($variant);
    }

    /**
     * @param  list<array{id: int, option: string}>  $attributes
     * @return array<string, mixed>
     */
    public function build(array $attributes, bool $includeStock): array
    {
        $payload = [
            'sku' => (string) $this->variant->sku,
            // A variant is only ever synced while its parent is being
            // actively pushed (archived parents never reach here — see
            // WooSyncRunner::skipArchived()), so `publish` is always right.
            // Explicit on every call, not just creation: WooCommerce trashes
            // variations individually when their parent is trashed, and
            // leaves them there — a later update has to re-assert this to
            // bring them back, or they silently stay unpurchasable even once
            // the parent itself is `publish` again.
            'status' => 'publish',
            'attributes' => $attributes,
        ];

        $payload += $this->priceFields();

        $image = $this->imageUrl();
        if ($image !== null) {
            $payload['image'] = ['src' => $image];
        }

        if ($includeStock) {
            $payload['manage_stock'] = true;
            $payload['stock_quantity'] = (int) ($this->variant->stock ?? 0);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function priceFields(): array
    {
        $list = PriceList::default();

        $price = $list !== null
            ? $this->variant->prices->firstWhere('price_list_id', $list->id)?->price
            : null;

        if ($price === null) {
            $this->warnings[] = __('pim.woosync.warn.no_price');

            return [];
        }

        return ['regular_price' => (string) $price];
    }

    public function imageUrl(): ?string
    {
        return $this->variant->getMainImageUrl();
    }

    public function imagesHash(): string
    {
        return md5((string) $this->imageUrl());
    }
}
