<?php

namespace Modules\Taxonomies\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Database\Factories\TaxonomyTermTranslationFactory;

/**
 * The name of a taxonomy term in one language.
 * One row per (taxonomy_term_id, language_id).
 */
class TaxonomyTermTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'taxonomy_term_id',
        'language_id',
        'locale',
        'name',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(TaxonomyTerm::class, 'taxonomy_term_id');
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

    protected static function newFactory(): TaxonomyTermTranslationFactory
    {
        return TaxonomyTermTranslationFactory::new();
    }
}
