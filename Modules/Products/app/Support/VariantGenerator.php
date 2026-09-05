<?php

namespace Modules\Products\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Localization\Support\Locales;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * Builds variant child products for a variable parent from a selection of
 * taxonomy values: one variant per combination of the picked values.
 *
 * The parent's translations are copied into each variant as the starting point
 * (editable afterwards); each variant gets the combination's terms, stock 0 and
 * the parent's status. Combinations whose SKU already exists are skipped, so the
 * action is safe to re-run after adding a value.
 *
 * The caller is responsible for keeping the combination count within
 * {@see self::MAX_COMBINATIONS}.
 */
class VariantGenerator
{
    public const MAX_COMBINATIONS = 100;

    /**
     * Cartesian product of the selected term ids, one group per taxonomy.
     * Groups are ordered by taxonomy id and empty groups are dropped, so the
     * result is stable and only reflects taxonomies that actually participate.
     *
     * @param  array<int|string, array<int, int|string>>  $termIdsByTaxonomy  [taxonomyId => [termId, ...]]
     * @return list<list<int>>  each inner list holds one term id per participating taxonomy
     */
    public static function combinations(array $termIdsByTaxonomy): array
    {
        ksort($termIdsByTaxonomy);

        $groups = [];

        foreach ($termIdsByTaxonomy as $termIds) {
            $termIds = array_values(array_unique(array_filter(array_map('intval', (array) $termIds))));

            if ($termIds !== []) {
                $groups[] = $termIds;
            }
        }

        if ($groups === []) {
            return [];
        }

        $result = [[]];

        foreach ($groups as $group) {
            $next = [];

            foreach ($result as $prefix) {
                foreach ($group as $termId) {
                    $next[] = [...$prefix, $termId];
                }
            }

            $result = $next;
        }

        return $result;
    }

    /**
     * Keep only the term selections for taxonomies that are enabled, as
     * [taxonomyId => [termId, ...]] with clean, de-duplicated int ids.
     *
     * @param  array<int|string, mixed>  $termsByTaxonomy
     * @param  array<int, int|string>  $enabledTaxonomyIds  empty keeps every taxonomy
     * @return array<int, array<int, int>>
     */
    public static function normalizeSelection(array $termsByTaxonomy, array $enabledTaxonomyIds = []): array
    {
        $enabled = array_map('intval', $enabledTaxonomyIds);
        $out = [];

        foreach ($termsByTaxonomy as $taxonomyId => $termIds) {
            if ($enabled !== [] && ! in_array((int) $taxonomyId, $enabled, true)) {
                continue;
            }

            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $termIds))));

            if ($ids !== []) {
                $out[(int) $taxonomyId] = $ids;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, TaxonomyTerm|null>  $terms
     */
    public static function proposedSku(string $parentSku, array $terms): string
    {
        $suffix = collect($terms)
            ->filter()
            ->map(fn (TaxonomyTerm $term): string => $term->slug)
            ->implode('-');

        return trim($parentSku).($suffix !== '' ? '-'.strtoupper($suffix) : '');
    }

    /**
     * @param  array<int, TaxonomyTerm|null>  $terms
     */
    public static function comboLabel(array $terms): string
    {
        return collect($terms)
            ->filter()
            ->map(fn (TaxonomyTerm $term): ?string => $term->name)
            ->implode(' · ');
    }

    /**
     * @param  array<int|string, array<int, int|string>>  $termIdsByTaxonomy
     * @param  array<int, array{sku?: string|null, name?: string|null}>  $overrides  keyed by combination index
     * @return array{created: int, skipped: int, variants: Collection<int, Product>}
     */
    public function generate(Product $parent, array $termIdsByTaxonomy, array $overrides = []): array
    {
        $combinations = self::combinations($termIdsByTaxonomy);

        if ($combinations === []) {
            return ['created' => 0, 'skipped' => 0, 'variants' => collect()];
        }

        $terms = TaxonomyTerm::query()
            ->whereIn('id', array_values(array_unique(array_merge(...$combinations))))
            ->with('translations')
            ->get()
            ->keyBy('id');

        $baseCode = Locales::baseCode();
        $parent->loadMissing('translations');

        $created = 0;
        $skipped = 0;
        $variants = collect();

        foreach ($combinations as $index => $termIds) {
            $comboTerms = array_map(fn (int $id): ?TaxonomyTerm => $terms->get($id), $termIds);

            $sku = trim((string) ($overrides[$index]['sku'] ?? ''))
                ?: self::proposedSku($parent->sku, $comboTerms);

            if (Product::query()->where('sku', $sku)->exists()) {
                $skipped++;

                continue;
            }

            $nameOverride = trim((string) ($overrides[$index]['name'] ?? '')) ?: null;

            $variant = DB::transaction(function () use ($parent, $sku, $termIds, $baseCode, $nameOverride): Product {
                $variant = Product::create([
                    'type' => ProductType::Variant->value,
                    'parent_id' => $parent->getKey(),
                    'sku' => $sku,
                    'status' => $parent->status,
                    'stock' => 0,
                ]);

                self::copyTranslations($parent, $variant, $baseCode, $nameOverride);

                $variant->taxonomyTerms()->sync($termIds);

                return $variant;
            });

            $variants->push($variant);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'variants' => $variants];
    }

    /**
     * Copy every translation of $from onto $to (base-language name optionally
     * overridden). Reused by the product import to seed a variant that has no
     * name of its own from its container.
     */
    public static function copyTranslations(Product $from, Product $to, string $baseCode, ?string $baseNameOverride): void
    {
        foreach ($from->translations as $translation) {
            $isBase = Locales::codeFor((int) $translation->language_id) === $baseCode;

            $to->translations()->create([
                'language_id' => $translation->language_id,
                'name' => $isBase && $baseNameOverride !== null ? $baseNameOverride : $translation->name,
                'description' => $translation->description,
            ]);
        }
    }
}
