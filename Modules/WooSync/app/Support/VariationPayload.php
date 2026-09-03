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
 * Stock is never built here — {@see WooSyncRunner::syncVariation()} merges it
 * in separately after reconciling it through the same delta model a simple
 * product's stock gets (see {@see WooSyncRunner::reconcileStock()}), exactly
 * like {@see ProductPayload} already does for a simple product.
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
    public function build(array $attributes): array
    {
        $payload = [
            'sku' => (string) $this->variant->sku,
            // A variant only ever reaches here while both it and its parent
            // are being actively pushed — a whole archived parent is caught
            // by WooSyncRunner::skipArchived(), an individually archived
            // variant by WooSyncRunner::skipArchivedVariant() — so `publish`
            // is always right. Explicit on every call, not just creation:
            // WooCommerce trashes variations individually when their parent
            // is trashed, and leaves them there — a later update has to
            // re-assert this to bring them back, or they silently stay
            // unpurchasable even once the parent itself is `publish` again.
            'status' => 'publish',
            'attributes' => $attributes,
        ];

        $payload += $this->priceFields();

        $image = $this->imageUrl();
        if ($image !== null) {
            $payload['image'] = ['src' => $image];
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
