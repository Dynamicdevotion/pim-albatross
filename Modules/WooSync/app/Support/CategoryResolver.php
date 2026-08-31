<?php

namespace Modules\WooSync\Support;

use Illuminate\Support\Collection;
use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Modules\WooSync\Contracts\WooCommerceClient;
use Modules\WooSync\Models\WooSyncCategoryLink;

/**
 * Turns a product's terms in the "Categorie" taxonomy into native WooCommerce
 * product-category ids, creating any the store is missing (parent before
 * child, so the Woo tree mirrors the PIM one) and remembering each mapping in
 * {@see WooSyncCategoryLink} so the next sync does not re-resolve it.
 *
 * Matching is by base-language name within a parent, case-insensitively — the
 * same "match a term by its name" rule ImportGestionali uses. The taxonomy is
 * found by the slug `categorie` (plural, as in production).
 */
class CategoryResolver
{
    private const TAXONOMY_SLUG = 'categorie';

    /** @var array<int, int> in-run cache: PIM term id => Woo category id */
    private array $memo = [];

    public function __construct(private readonly WooCommerceClient $client) {}

    /**
     * The product's terms that belong to the "Categorie" taxonomy.
     *
     * @return Collection<int, TaxonomyTerm>
     */
    public static function categoryTerms(Product $product): Collection
    {
        $taxonomyId = Taxonomy::query()->where('slug', self::TAXONOMY_SLUG)->value('id');

        if ($taxonomyId === null) {
            return collect();
        }

        return $product->taxonomyTerms
            ->where('taxonomy_id', $taxonomyId)
            ->values();
    }

    /**
     * @return list<int> Woo category ids for the given "Categorie" terms
     */
    public function idsFor(TaxonomyTerm ...$terms): array
    {
        $ids = [];

        foreach ($terms as $term) {
            $ids[] = $this->resolve($term);
        }

        return array_values(array_unique($ids));
    }

    private function resolve(TaxonomyTerm $term): int
    {
        if (isset($this->memo[$term->id])) {
            return $this->memo[$term->id];
        }

        $link = WooSyncCategoryLink::query()->firstWhere('taxonomy_term_id', $term->id);

        if ($link !== null) {
            return $this->memo[$term->id] = $link->woocommerce_category_id;
        }

        $parent = $term->parent_id !== null ? $term->parent()->first() : null;
        $parentWooId = $parent !== null ? $this->resolve($parent) : 0;

        $wooId = $this->findOrCreate($term, $parentWooId);

        WooSyncCategoryLink::query()->updateOrCreate(
            ['taxonomy_term_id' => $term->id],
            ['woocommerce_category_id' => $wooId],
        );

        return $this->memo[$term->id] = $wooId;
    }

    private function findOrCreate(TaxonomyTerm $term, int $parentWooId): int
    {
        $name = $term->translate(Locales::baseCode())?->name ?? $term->slug;

        $candidates = $this->client->listCategories([
            'search' => $name,
            'per_page' => 100,
        ]);

        foreach ($candidates as $candidate) {
            if (mb_strtolower((string) ($candidate['name'] ?? '')) === mb_strtolower((string) $name)
                && (int) ($candidate['parent'] ?? 0) === $parentWooId) {
                return (int) $candidate['id'];
            }
        }

        $created = $this->client->createCategory(array_filter([
            'name' => $name,
            'parent' => $parentWooId !== 0 ? $parentWooId : null,
        ], static fn ($value): bool => $value !== null));

        return (int) $created['id'];
    }
}
