<?php

namespace Modules\Taxonomies\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Database\Factories\TaxonomyTermFactory;
use Modules\Taxonomies\Models\Concerns\HasGeneratedSlug;

/**
 * A single value of a taxonomy (e.g. "Abbigliamento" in "Categoria"), optionally
 * nested under a parent term of the same taxonomy.
 */
class TaxonomyTerm extends Model
{
    use HasFactory;
    use HasGeneratedSlug;

    protected $fillable = [
        'taxonomy_id',
        'parent_id',
        'name',
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

    /**
     * Products tagged with this term.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_taxonomy_term');
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

    protected function slugScope(): array
    {
        return ['taxonomy_id' => $this->taxonomy_id];
    }

    protected static function newFactory(): TaxonomyTermFactory
    {
        return TaxonomyTermFactory::new();
    }
}
