<?php

namespace Modules\Localization\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Localization\Database\Factories\ProductTranslationFactory;
use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;

/**
 * A single translation of a product's content into one language.
 *
 * One row per (product_id, language_id). No automatic fallback: a missing row
 * means the content is simply not available in that language.
 */
class ProductTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'language_id',
        'locale',
        'name',
        'description',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Ergonomic bridge: read/write the language by its code
     * (`$translation->locale`, `create(['locale' => 'it'])`).
     */
    protected function locale(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->relationLoaded('language')
                ? $this->language?->code
                : Locales::codeFor((int) $this->language_id),
            set: fn (string $code): array => ['language_id' => Locales::idFor($code)],
        );
    }

    protected static function newFactory(): ProductTranslationFactory
    {
        return ProductTranslationFactory::new();
    }
}
