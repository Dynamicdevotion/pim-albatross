<?php

namespace Modules\Products\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'type' => ProductType::Simple->value,
            'parent_id' => null,
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'external_id' => null,
            'status' => 'draft',
            'stock' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => 'active']);
    }

    /**
     * Give the product a full set of shipping dimensions (kg / cm).
     */
    public function withDimensions(): static
    {
        return $this->state(fn (): array => [
            'weight' => 0.750,
            'length' => 30,
            'width' => 20,
            'height' => 10,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => 'archived']);
    }

    /**
     * A container product: no own stock, variants added separately.
     */
    public function variable(): static
    {
        return $this->state(fn (): array => [
            'type' => ProductType::Variable->value,
            'stock' => null,
        ]);
    }

    /**
     * A variant child of the given variable product.
     */
    public function variantOf(Product $parent): static
    {
        return $this->state(fn (): array => [
            'type' => ProductType::Variant->value,
            'parent_id' => $parent->getKey(),
            'stock' => 0,
        ]);
    }

    /**
     * Attach a fake main image after creation. Intended for tests, which run
     * with `Storage::fake('public')`.
     */
    public function withMainImage(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $product->addMedia(UploadedFile::fake()->image('main.jpg', 400, 400))
                ->toMediaCollection('main_image');
        });
    }
}
