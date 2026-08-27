<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Localization\Enums\Locale;
use Modules\Localization\Models\ProductTranslation;
// use Modules\Products\Database\Factories\ProductFactory;

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
     * All per-locale translations of this product's content.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /**
     * The translation for a given locale, or null if it does not exist.
     *
     * No fallback: a missing translation returns null.
     */
    public function translate(Locale|string $locale): ?ProductTranslation
    {
        $value = $locale instanceof Locale ? $locale->value : $locale;

        return $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $value)
            : $this->translations()->where('locale', $value)->first();
    }

    // protected static function newFactory(): ProductFactory
    // {
    //     // return ProductFactory::new();
    // }
}
