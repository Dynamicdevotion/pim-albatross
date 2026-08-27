<?php

namespace Modules\Taxonomies\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Database\Factories\TaxonomyTermFactory;

/**
 * A single value of a taxonomy (e.g. "Abbigliamento"), optionally nested under a
 * parent term. Its name is translatable (taxonomy_term_translations).
 */
class TaxonomyTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'taxonomy_id',
        'parent_id',
        'slug',
    ];

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_taxonomy_term');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(TaxonomyTermTranslation::class);
    }

    public function translate(string $locale): ?TaxonomyTermTranslation
    {
        $languageId = Locales::idFor($locale);

        if ($languageId === null) {
            return null;
        }

        return $this->relationLoaded('translations')
            ? $this->translations->firstWhere('language_id', $languageId)
            : $this->translations()->where('language_id', $languageId)->first();
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->translate(Locales::baseCode())?->name);
    }

    /**
     * IDs of this term and every term below it, for cycle-free parent pickers.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->children()->get() as $child) {
            $ids[] = $child->getKey();
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    protected static function newFactory(): TaxonomyTermFactory
    {
        return TaxonomyTermFactory::new();
    }
}
