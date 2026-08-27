<?php

namespace Modules\Taxonomies\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Database\Factories\TaxonomyFactory;

/**
 * A user-defined type of classification (e.g. "Categoria", "Colore"). Its name
 * is translatable (taxonomy_translations); `slug` is the stable identifier.
 */
class Taxonomy extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
    ];

    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class);
    }

    public function rootTerms(): HasMany
    {
        return $this->terms()->whereNull('parent_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(TaxonomyTranslation::class);
    }

    /**
     * The name row for a language code, or null (no fallback).
     */
    public function translate(string $locale): ?TaxonomyTranslation
    {
        $languageId = Locales::idFor($locale);

        if ($languageId === null) {
            return null;
        }

        return $this->relationLoaded('translations')
            ? $this->translations->firstWhere('language_id', $languageId)
            : $this->translations()->where('language_id', $languageId)->first();
    }

    /**
     * Read-only base-language name, so `$taxonomy->name`, table columns and
     * recordTitleAttribute keep working. Writes go through translations.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->translate(Locales::baseCode())?->name);
    }

    protected static function newFactory(): TaxonomyFactory
    {
        return TaxonomyFactory::new();
    }
}
