<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Localization\Models\ProductTranslation;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\ProductPrice;
use Modules\Products\Database\Factories\ProductFactory;
use Modules\Products\Enums\ProductType;
use Modules\Products\Exceptions\CannotChangeProductType;
use Modules\Taxonomies\Models\TaxonomyTerm;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'type',
        'parent_id',
        'sku',
        'external_id',
        'status',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'stock' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Backstop for every write path (Filament, tinker, seeders, future API):
        // a variable product never carries its own stock, and the
        // simple / variable / variant shape stays internally consistent.
        static::saving(function (Product $product): void {
            if ($product->type === ProductType::Variable) {
                $product->stock = null;
            }

            $product->assertConsistentType();
        });
    }

    // ---- relationships ----------------------------------------------------

    /**
     * All per-language translations of this product's content.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /**
     * Taxonomy terms assigned to this product (across any taxonomy).
     */
    public function taxonomyTerms(): BelongsToMany
    {
        return $this->belongsToMany(TaxonomyTerm::class, 'product_taxonomy_term');
    }

    /**
     * This product's price in each price list.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * The variable product this variant belongs to (null for simple/variable).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The variant products grouped under this variable product.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // ---- type helpers ---------------------------------------------------

    public function isSimple(): bool
    {
        return $this->type === ProductType::Simple;
    }

    public function isVariable(): bool
    {
        return $this->type === ProductType::Variable;
    }

    public function isVariant(): bool
    {
        return $this->type === ProductType::Variant;
    }

    // ---- translations -------------------------------------------------

    /**
     * The translation for a given language code, or null if it does not exist.
     *
     * No fallback: a missing translation returns null.
     */
    public function translate(string $locale): ?ProductTranslation
    {
        $languageId = Locales::idFor($locale);

        if ($languageId === null) {
            return null;
        }

        return $this->relationLoaded('translations')
            ? $this->translations->firstWhere('language_id', $languageId)
            : $this->translations()->where('language_id', $languageId)->first();
    }

    // ---- invariants -------------------------------------------------

    /**
     * @throws CannotChangeProductType
     */
    protected function assertConsistentType(): void
    {
        $type = $this->type instanceof ProductType
            ? $this->type
            : ProductType::from((string) ($this->type ?? ProductType::Simple->value));

        // A container that already has variants cannot become something else.
        if ($this->exists
            && $this->getRawOriginal('type') === ProductType::Variable->value
            && $type !== ProductType::Variable
            && $this->variants()->exists()
        ) {
            throw CannotChangeProductType::hasVariants();
        }

        if ($type === ProductType::Variant && $this->parent_id === null) {
            throw CannotChangeProductType::variantNeedsParent();
        }

        if ($type !== ProductType::Variant && $this->parent_id !== null) {
            throw CannotChangeProductType::onlyVariantHasParent();
        }

        if ($type === ProductType::Variant && $this->isDirty('parent_id')) {
            $parent = static::query()->find($this->parent_id);

            if ($parent !== null && ! $parent->isVariable()) {
                throw CannotChangeProductType::parentNotVariable();
            }
        }
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
