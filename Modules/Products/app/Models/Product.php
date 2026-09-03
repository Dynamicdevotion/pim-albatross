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
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    /**
     * Image formats accepted for both media collections.
     */
    public const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'type',
        'parent_id',
        'sku',
        'barcode',
        'external_id',
        'status',
        'stock',
        'weight',
        'length',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'stock' => 'integer',
            'weight' => 'decimal:3',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Backstop for every write path (Filament, tinker, seeders, future API):
        // a variable product never carries its own stock or shipping
        // dimensions, and the simple / variable / variant shape stays
        // internally consistent.
        static::saving(function (Product $product): void {
            if ($product->type === ProductType::Variable) {
                $product->stock = null;
                $product->weight = null;
                $product->length = null;
                $product->width = null;
                $product->height = null;
            }

            $product->assertConsistentType();
        });

        // Spatie's InteractsWithMedia removes THIS product's media on delete,
        // but a variable's variants are removed by the `parent_id` FK cascade
        // (no model events fire), so their files would be orphaned. Clean them
        // here — same idea as the deleting hooks on ImportRecord / ExportRecord.
        static::deleting(function (Product $product): void {
            if ($product->isVariable()) {
                $product->variants()->cursor()->each(fn (Product $variant) => $variant->deleteAllMedia());
            }
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

    /**
     * Products suggested as an upgrade/alternative while viewing this one.
     * Directional (this product doesn't automatically become an upsell of
     * its own upsells) and — enforced in the form and again at save time,
     * never at the DB level — only ever between `simple`/`variable`
     * products, never a `variant`.
     */
    public function upsells(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_upsells', 'product_id', 'related_product_id');
    }

    /**
     * Products suggested alongside this one (e.g. at checkout). Same shape
     * and constraints as {@see upsells()}.
     */
    public function crossSells(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_cross_sells', 'product_id', 'related_product_id');
    }

    // ---- media --------------------------------------------------------

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main_image')
            ->singleFile()
            ->acceptsMimeTypes(self::IMAGE_MIME_TYPES);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(self::IMAGE_MIME_TYPES);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Generated synchronously on upload: the shared host runs no queue
        // worker, so a queued conversion would never be built. Fit::Crop
        // fills a true 300x300 square (centre crop) — a uniform tile for the
        // list column and the form's grid previews, not a letterboxed image.
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 300, 300)
            ->nonQueued();
    }

    /**
     * URL of this product's main image. A variant with no image of its own
     * falls back to its parent's — the same "own value, then parent" rule
     * used elsewhere for variant names and SKUs. Returns null when neither
     * has one; falls back to the original file when the requested conversion
     * has not been generated.
     */
    public function getMainImageUrl(string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia('main_image')
            ?? ($this->isVariant() ? $this->parent?->getFirstMedia('main_image') : null);

        if ($media === null) {
            return null;
        }

        return ($conversion !== '' && $media->hasGeneratedConversion($conversion))
            ? $media->getUrl($conversion)
            : $media->getUrl();
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
