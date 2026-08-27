<?php

namespace Modules\Taxonomies\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Database\Factories\TaxonomyTranslationFactory;

/**
 * The name of a taxonomy in one language. One row per (taxonomy_id, language_id).
 */
class TaxonomyTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'taxonomy_id',
        'language_id',
        'locale',
        'name',
    ];

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    protected function locale(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->relationLoaded('language')
                ? $this->language?->code
                : Locales::codeFor((int) $this->language_id),
            set: fn (string $code): array => ['language_id' => Locales::idFor($code)],
        );
    }

    protected static function newFactory(): TaxonomyTranslationFactory
    {
        return TaxonomyTranslationFactory::new();
    }
}
