<?php

namespace Modules\WooSync\Support;

use Modules\Products\Models\Product;

/**
 * Builds the WooCommerce payload for a variable product's *parent* — the
 * same common fields a simple product gets ({@see ProductPayload}: name,
 * description, status, images, categories) plus `type: variable` and the
 * `attributes` array (`variation: true`, each attribute's distinct option
 * labels across the product's current variants). Price/weight/dimensions
 * never appear: a variable parent never carries them (enforced at the
 * `Product` model level), and {@see ProductPayload::priceFields()} already
 * knows not to warn about that.
 */
class VariableProductPayload
{
    /** @var list<string> */
    public array $warnings = [];

    private readonly ProductPayload $base;

    public function __construct(private readonly Product $product)
    {
        $this->base = ProductPayload::for($product);
    }

    public static function for(Product $product): self
    {
        return new self($product);
    }

    /**
     * @param  list<int>  $categoryIds
     * @param  list<array{id: int, variation: bool, options: list<string>}>  $attributes
     * @return array<string, mixed>
     */
    public function build(array $categoryIds, array $attributes): array
    {
        $body = $this->base->build($categoryIds);
        $this->warnings = $this->base->warnings;

        $body['type'] = 'variable';
        $body['attributes'] = $attributes;

        return $body;
    }

    public function imagesHash(): string
    {
        return $this->base->imagesHash();
    }
}
