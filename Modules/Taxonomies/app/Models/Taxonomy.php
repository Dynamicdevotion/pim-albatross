<?php

namespace Modules\Taxonomies\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Taxonomies\Database\Factories\TaxonomyFactory;
use Modules\Taxonomies\Models\Concerns\HasGeneratedSlug;

/**
 * A user-defined type of classification (e.g. "Categoria", "Colore").
 * Its individual values are TaxonomyTerm rows.
 */
class Taxonomy extends Model
{
    use HasFactory;
    use HasGeneratedSlug;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * All terms belonging to this taxonomy (flat; hierarchy is on the term).
     */
    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class);
    }

    /**
     * Only the top-level terms of this taxonomy.
     */
    public function rootTerms(): HasMany
    {
        return $this->terms()->whereNull('parent_id');
    }

    protected static function newFactory(): TaxonomyFactory
    {
        return TaxonomyFactory::new();
    }
}
