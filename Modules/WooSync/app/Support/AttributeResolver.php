<?php

namespace Modules\WooSync\Support;

use Illuminate\Support\Collection;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;
use Modules\WooSync\Contracts\WooCommerceClient;
use Modules\WooSync\Models\WooSyncAttributeLink;
use Modules\WooSync\Models\WooSyncAttributeTermLink;

/**
 * Turns the taxonomies used to generate a variable product's variants into
 * native WooCommerce global product attributes (`pa_*`), and their terms into
 * the attribute's own terms — creating whatever the store is missing and
 * remembering each mapping ({@see WooSyncAttributeLink},
 * {@see WooSyncAttributeTermLink}) so the next sync does not re-resolve it.
 *
 * There is no stored record in the Products module of "which taxonomies
 * generated this product's variants" — the "Genera varianti" wizard is
 * transient UI state, and `taxonomyTerms()->sync()` leaves each variant with
 * *only* the terms of its own combination (never the parent's descriptive
 * ones, e.g. "Categoria"). That makes the union of taxonomy ids across a
 * variable's variants exactly the set of variant-defining taxonomies, with no
 * new schema needed on the Products side — see {@see variantTaxonomies()}.
 * The "categorie" taxonomy is excluded even so, as a safety net: it already
 * has its own resolver ({@see CategoryResolver}) and meaning on the Woo side.
 *
 * Matching is by base-language name, case-insensitively — same rule
 * `CategoryResolver` uses for categories.
 */
class AttributeResolver
{
    private const EXCLUDED_TAXONOMY_SLUG = 'categorie';

    /** @var array<int, int> in-run cache: taxonomy id => Woo attribute id */
    private array $attributeMemo = [];

    /** @var array<int, int> in-run cache: PIM term id => Woo term id */
    private array $termMemo = [];

    public function __construct(private readonly WooCommerceClient $client) {}

    /**
     * The taxonomies used across all of the given variable product's current
     * variants — i.e. the taxonomies that define its variations.
     *
     * @return Collection<int, Taxonomy> keyed by taxonomy id
     */
    public static function variantTaxonomies(Product $variable): Collection
    {
        $excludedId = Taxonomy::query()->where('slug', self::EXCLUDED_TAXONOMY_SLUG)->value('id');

        $taxonomyIds = $variable->variants()
            ->with('taxonomyTerms')
            ->get()
            ->flatMap(fn (Product $variant) => $variant->taxonomyTerms->pluck('taxonomy_id'))
            ->unique()
            ->reject(fn (int $id): bool => $id === $excludedId)
            ->values();

        if ($taxonomyIds->isEmpty()) {
            return collect();
        }

        return Taxonomy::query()->whereIn('id', $taxonomyIds)->get()->keyBy('id');
    }

    public function attributeIdFor(Taxonomy $taxonomy): int
    {
        if (isset($this->attributeMemo[$taxonomy->id])) {
            return $this->attributeMemo[$taxonomy->id];
        }

        $link = WooSyncAttributeLink::query()->firstWhere('taxonomy_id', $taxonomy->id);

        if ($link !== null) {
            return $this->attributeMemo[$taxonomy->id] = $link->woocommerce_attribute_id;
        }

        $wooId = $this->findOrCreateAttribute($taxonomy);

        WooSyncAttributeLink::query()->updateOrCreate(
            ['taxonomy_id' => $taxonomy->id],
            ['woocommerce_attribute_id' => $wooId],
        );

        return $this->attributeMemo[$taxonomy->id] = $wooId;
    }

    public function termIdFor(TaxonomyTerm $term, int $attributeId): int
    {
        if (isset($this->termMemo[$term->id])) {
            return $this->termMemo[$term->id];
        }

        $link = WooSyncAttributeTermLink::query()->firstWhere('taxonomy_term_id', $term->id);

        if ($link !== null) {
            return $this->termMemo[$term->id] = $link->woocommerce_term_id;
        }

        $wooId = $this->findOrCreateTerm($term, $attributeId);

        WooSyncAttributeTermLink::query()->updateOrCreate(
            ['taxonomy_term_id' => $term->id],
            ['woocommerce_attribute_id' => $attributeId, 'woocommerce_term_id' => $wooId],
        );

        return $this->termMemo[$term->id] = $wooId;
    }

    private function findOrCreateAttribute(Taxonomy $taxonomy): int
    {
        $name = $taxonomy->name ?? $taxonomy->slug;

        $candidates = $this->client->listAttributes(['search' => $name]);

        foreach ($candidates as $candidate) {
            if (mb_strtolower((string) ($candidate['name'] ?? '')) === mb_strtolower((string) $name)) {
                return (int) $candidate['id'];
            }
        }

        $created = $this->client->createAttribute(['name' => $name, 'type' => 'select']);

        return (int) $created['id'];
    }

    private function findOrCreateTerm(TaxonomyTerm $term, int $attributeId): int
    {
        $name = $term->name ?? $term->slug;

        $candidates = $this->client->listAttributeTerms($attributeId, ['search' => $name]);

        foreach ($candidates as $candidate) {
            if (mb_strtolower((string) ($candidate['name'] ?? '')) === mb_strtolower((string) $name)) {
                return (int) $candidate['id'];
            }
        }

        $created = $this->client->createAttributeTerm($attributeId, ['name' => $name]);

        return (int) $created['id'];
    }
}
