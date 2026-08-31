<?php

namespace Modules\ImportGestionali\Support;

use Illuminate\Support\Str;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Models\Taxonomy;
use Modules\Taxonomies\Models\TaxonomyTerm;

/**
 * Resolves the term names in a mapped taxonomy column to {@see TaxonomyTerm}
 * ids, scoped to the specific taxonomy the column was mapped to (more precise
 * than a system-wide lookup).
 *
 *  - matching is by base-language name (case-insensitive, trimmed), then slug;
 *  - a name with no match is reported as `missing`, or — with
 *    `createMissingTerms` — created as a root term of that taxonomy;
 *  - a per-run cache means a term created for one row is reused by later rows,
 *    and each taxonomy's terms are loaded only once.
 */
final class TaxonomyTermResolver
{
    private readonly int $baseLanguageId;

    /**
     * taxonomyId => ['taxonomy' => ?Taxonomy, 'byName' => array<string,int>, 'bySlug' => array<string,int>]
     *
     * @var array<int, array{taxonomy: ?Taxonomy, byName: array<string, int>, bySlug: array<string, int>}>
     */
    private array $cache = [];

    public function __construct(
        private readonly bool $createMissingTerms = false,
    ) {
        $this->baseLanguageId = (int) Locales::base()->id;
    }

    /**
     * @param  list<string>  $names  trimmed, non-empty term names from the cell
     */
    public function resolve(int $taxonomyId, array $names, bool $dryRun): TaxonomyResolution
    {
        $bucket = &$this->bucket($taxonomyId);

        if ($bucket['taxonomy'] === null) {
            return TaxonomyResolution::gone($taxonomyId);
        }

        $terms = [];

        foreach ($names as $name) {
            $termId = $bucket['byName'][$this->normalize($name)]
                ?? $bucket['bySlug'][Str::slug($name)]
                ?? null;

            if ($termId !== null) {
                $terms[] = ['name' => $name, 'status' => TaxonomyResolution::FOUND, 'term_id' => $termId];

                continue;
            }

            if (! $this->createMissingTerms) {
                $terms[] = ['name' => $name, 'status' => TaxonomyResolution::MISSING, 'term_id' => null];

                continue;
            }

            if ($dryRun) {
                $terms[] = ['name' => $name, 'status' => TaxonomyResolution::WILL_CREATE, 'term_id' => null];

                continue;
            }

            $slug = $this->uniqueSlug($taxonomyId, Str::slug($name) ?: 'termine');
            $termId = $this->createTerm($taxonomyId, $name, $slug);

            // Cache so later names in this run (and later rows) reuse it.
            $bucket['byName'][$this->normalize($name)] = $termId;
            $bucket['bySlug'][$slug] = $termId;

            $terms[] = ['name' => $name, 'status' => TaxonomyResolution::CREATED, 'term_id' => $termId];
        }

        return new TaxonomyResolution(
            $taxonomyId,
            $bucket['taxonomy']->name ?? $bucket['taxonomy']->slug,
            $terms,
        );
    }

    /**
     * @return array{taxonomy: ?Taxonomy, byName: array<string, int>, bySlug: array<string, int>}
     */
    private function &bucket(int $taxonomyId): array
    {
        if (! array_key_exists($taxonomyId, $this->cache)) {
            $this->cache[$taxonomyId] = $this->loadBucket($taxonomyId);
        }

        return $this->cache[$taxonomyId];
    }

    /**
     * @return array{taxonomy: ?Taxonomy, byName: array<string, int>, bySlug: array<string, int>}
     */
    private function loadBucket(int $taxonomyId): array
    {
        $taxonomy = Taxonomy::query()->with('translations')->find($taxonomyId);

        if ($taxonomy === null) {
            return ['taxonomy' => null, 'byName' => [], 'bySlug' => []];
        }

        $byName = [];
        $bySlug = [];

        TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomyId)
            ->with('translations')
            ->get()
            ->each(function (TaxonomyTerm $term) use (&$byName, &$bySlug): void {
                $bySlug[$term->slug] = $term->id;

                $name = $term->translate(Locales::baseCode())?->name;

                if ($name !== null) {
                    $byName[$this->normalize($name)] = $term->id;
                }
            });

        return ['taxonomy' => $taxonomy, 'byName' => $byName, 'bySlug' => $bySlug];
    }

    private function createTerm(int $taxonomyId, string $name, string $slug): int
    {
        $term = TaxonomyTerm::create([
            'taxonomy_id' => $taxonomyId,
            'parent_id' => null,
            'slug' => $slug,
        ]);

        $term->translations()->create([
            'language_id' => $this->baseLanguageId,
            'name' => $name,
        ]);

        return $term->id;
    }

    private function uniqueSlug(int $taxonomyId, string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (
            isset($this->cache[$taxonomyId]['bySlug'][$slug])
            || TaxonomyTerm::query()->where('taxonomy_id', $taxonomyId)->where('slug', $slug)->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
