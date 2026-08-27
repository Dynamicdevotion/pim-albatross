<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Localization\Models\ProductTranslation;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\ProductPrice;
use Modules\Products\Database\Factories\ProductFactory;
use Modules\Taxonomies\Models\TaxonomyTerm;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'sku',
        'external_id',
        'status',
    ];

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

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
