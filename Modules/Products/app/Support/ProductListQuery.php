<?php

namespace Modules\Products\Support;

use Illuminate\Database\Eloquent\Builder;
use Modules\ExportProdotti\Support\ExportRunner;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Products\Filament\Resources\Products\Tables\ProductsTable;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * The single source of truth for the products-list query: the base scope
 * (top-level rows only, with the list's eager loads) and every one of the
 * filter clauses behind the filter drawer.
 *
 * {@see ProductsTable}
 * wires each Filament filter's `query()` straight to the matching method here,
 * and {@see ExportRunner} rebuilds the exact
 * same query from a saved `tableFilters` snapshot — so an export can never
 * drift from what the list shows.
 */
class ProductListQuery
{
    /**
     * The list's base scope, applied to an existing query (the table's
     * `modifyQueryUsing`). Top-level products only — variants are managed
     * inside their parent.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public static function applyBase(Builder $query): Builder
    {
        return $query
            ->whereNull('parent_id')
            ->withCount('variants')
            ->with(['translations', 'taxonomyTerms.taxonomy', 'media']);
    }

    /**
     * A fresh base query.
     *
     * @return Builder<Product>
     */
    public static function base(): Builder
    {
        return static::applyBase(Product::query());
    }

    /**
     * A fresh base query with every active filter applied.
     *
     * @param  array<string, mixed>  $filters  the page's `tableFilters` shape:
     *                                         [filterName => filterState]
     * @return Builder<Product>
     */
    public static function for(array $filters): Builder
    {
        return static::apply(static::base(), $filters);
    }

    /**
     * Apply the whole filter set to a query.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        static::search($query, $filters['search'] ?? []);
        static::type($query, $filters['type'] ?? []);
        static::status($query, $filters['status'] ?? []);
        static::missingTranslation($query, $filters['missing_translation'] ?? []);
        static::taxonomyTerms($query, $filters['taxonomy_terms'] ?? []);
        static::price($query, $filters['price'] ?? []);
        static::stock($query, $filters['stock'] ?? []);

        return $query;
    }

    /**
     * Free-text search on the base-language name or the SKU.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<Product>
     */
    public static function search(Builder $query, array $data): Builder
    {
        $term = trim((string) ($data['term'] ?? ''));

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner
                ->whereHas(
                    'translations',
                    fn (Builder $relation): Builder => $relation
                        ->where('language_id', Locales::idFor(Locales::baseCode()))
                        ->where('name', 'like', "%{$term}%"),
                )
                ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<Product>
     */
    public static function type(Builder $query, array $data): Builder
    {
        return filled($data['value'] ?? null)
            ? $query->where('type', $data['value'])
            : $query;
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<Product>
     */
    public static function status(Builder $query, array $data): Builder
    {
        return filled($data['value'] ?? null)
            ? $query->where('status', $data['value'])
            : $query;
    }

    /**
     * Products with no translation row in the chosen language.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<Product>
     */
    public static function missingTranslation(Builder $query, array $data): Builder
    {
        return filled($data['value'] ?? null)
            ? $query->whereDoesntHave(
                'translations',
                fn (Builder $relation): Builder => $relation->where('language_id', Locales::idFor($data['value'])),
            )
            : $query;
    }

    /**
     * Faceted taxonomy filter: AND across taxonomies, OR within one, each
     * selected term expanded to its subtree.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<Product>
     */
    public static function taxonomyTerms(Builder $query, array $data): Builder
    {
        $ids = array_values(array_filter(array_map('intval', $data['terms'] ?? [])));

        if ($ids === []) {
            return $query;
        }

        $byTaxonomy = [];

        foreach (TaxonomyTerm::query()->whereIn('id', $ids)->get() as $term) {
            $subtree = [$term->getKey(), ...$term->descendantIds()];
            $byTaxonomy[$term->taxonomy_id] = array_merge($byTaxonomy[$term->taxonomy_id] ?? [], $subtree);
        }

        foreach ($byTaxonomy as $termIds) {
            $query->whereHas(
                'taxonomyTerms',
                fn (Builder $relation): Builder => $relation
                    ->whereIn('taxonomy_terms.id', array_values(array_unique($termIds))),
            );
        }

        return $query;
    }

    /**
     * Price presence + range, on one price list (default: the default list).
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<Product>
     */
    public static function price(Builder $query, array $data): Builder
    {
        $listId = (int) ($data['price_list_id'] ?? 0)
            ?: (int) (PriceList::query()->where('is_default', true)->value('id') ?? 0);

        if ($listId === 0) {
            return $query;
        }

        $presence = $data['presence'] ?? null;
        $min = filled($data['min'] ?? null) ? (float) $data['min'] : null;
        $max = filled($data['max'] ?? null) ? (float) $data['max'] : null;

        if ($presence === 'without') {
            return $query->whereDoesntHave(
                'prices',
                fn (Builder $relation): Builder => $relation->where('price_list_id', $listId),
            );
        }

        if ($presence === 'with' || $min !== null || $max !== null) {
            return $query->whereHas('prices', fn (Builder $relation): Builder => $relation
                ->where('price_list_id', $listId)
                ->when($min !== null, fn (Builder $q): Builder => $q->where('price', '>=', $min))
                ->when($max !== null, fn (Builder $q): Builder => $q->where('price', '<=', $max)));
        }

        return $query;
    }

    /**
     * Stock level. `whereNotNull('stock')` keeps variable containers out.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $data
     * @return Builder<Product>
     */
    public static function stock(Builder $query, array $data): Builder
    {
        $threshold = (int) config('products.low_stock_threshold', 5);

        return match ($data['level'] ?? null) {
            'zero' => $query->whereNotNull('stock')->where('stock', 0),
            'low' => $query->whereNotNull('stock')->whereBetween('stock', [1, $threshold]),
            default => $query,
        };
    }

    /**
     * Term id => "Taxonomy: Term", grouped by taxonomy in a stable order.
     *
     * @return array<int, string>
     */
    public static function taxonomyTermOptions(): array
    {
        $options = [];

        foreach (Taxonomy::query()->with(['translations', 'terms.translations'])->get() as $taxonomy) {
            foreach ($taxonomy->terms as $term) {
                $options[$term->id] = "{$taxonomy->name}: {$term->name}";
            }
        }

        return $options;
    }
}
