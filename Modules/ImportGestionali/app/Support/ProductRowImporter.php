<?php

namespace Modules\ImportGestionali\Support;

use Illuminate\Support\Facades\DB;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Support\ProductPriceMatrix;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;

/**
 * Turns one mapped row (target => raw value) into a created/updated/skipped
 * outcome for a simple product. Matching is by SKU. On update, an empty or
 * unmapped value leaves the existing value untouched.
 *
 * Columns mapped to a taxonomy (`taxonomy:{id}`, see {@see MappingTarget}) hold
 * one or more term names separated by `|`; they are resolved against that
 * taxonomy and linked through `product_taxonomy_term`.
 *
 * `dryRun` runs every check and reports the outcome without writing — the
 * preview and the real import share this exact code path.
 */
final class ProductRowImporter
{
    private const STATUS_MAP = [
        'draft' => 'draft',
        'bozza' => 'draft',
        'active' => 'active',
        'attivo' => 'active',
        'attiva' => 'active',
        'pubblicato' => 'active',
        'pubblicata' => 'active',
        'archived' => 'archived',
        'archiviato' => 'archived',
        'archiviata' => 'archived',
    ];

    public function __construct(
        private readonly int $defaultPriceListId,
        private readonly int $baseLanguageId,
        private readonly TaxonomyTermResolver $taxonomyResolver,
        private readonly bool $replaceTaxonomyTerms = false,
    ) {}

    public static function make(bool $createMissingTerms = false, bool $replaceTaxonomyTerms = false): self
    {
        return new self(
            (int) (PriceList::default()?->id ?? 0),
            (int) Locales::base()->id,
            new TaxonomyTermResolver($createMissingTerms),
            $replaceTaxonomyTerms,
        );
    }

    /**
     * @param  array<string, string>  $mapped  target => raw value
     * @param  array<string, int>  $seenSkus  lower-cased sku => the line it first appeared on
     */
    public function import(array $mapped, int $line, bool $updateExisting, array &$seenSkus, bool $dryRun = false): RowOutcome
    {
        $sku = trim($mapped['sku'] ?? '');

        if ($sku === '') {
            return RowOutcome::skipped($line, __('pim.import.issue.sku_missing', ['line' => $line]));
        }

        $key = mb_strtolower($sku);

        if (isset($seenSkus[$key])) {
            return RowOutcome::skipped($line, __('pim.import.issue.sku_dup_in_file', [
                'line' => $line, 'sku' => $sku, 'first' => $seenSkus[$key],
            ]));
        }

        $seenSkus[$key] = $line;

        $existing = Product::query()->where('sku', $sku)->first();

        if ($existing !== null && ! $updateExisting) {
            return RowOutcome::skipped($line, __('pim.import.issue.sku_exists', ['line' => $line, 'sku' => $sku]));
        }

        $errors = [];
        $price = $this->number($mapped['price'] ?? null, 'price', $line, $errors);
        $stock = $this->integer($mapped['stock'] ?? null, $line, $errors);
        $weight = $this->number($mapped['weight'] ?? null, 'weight', $line, $errors);
        $length = $this->number($mapped['length'] ?? null, 'length', $line, $errors);
        $width = $this->number($mapped['width'] ?? null, 'width', $line, $errors);
        $height = $this->number($mapped['height'] ?? null, 'height', $line, $errors);
        $status = $this->status($mapped['status'] ?? null, $line, $errors);

        if ($errors !== []) {
            return RowOutcome::skipped($line, $errors[0]);
        }

        $name = trim($mapped['name'] ?? '');
        $description = trim($mapped['description'] ?? '');

        if ($existing === null && $name === '') {
            return RowOutcome::skipped($line, __('pim.import.issue.name_missing', ['line' => $line]));
        }

        $taxonomyTargets = $this->taxonomyTargets($mapped);

        if ($dryRun) {
            $outcome = $existing !== null ? RowOutcome::updated($line) : RowOutcome::created($line);

            if ($taxonomyTargets === []) {
                return $outcome;
            }

            $resolutions = [];

            foreach ($taxonomyTargets as $taxonomyId => $names) {
                $resolutions[] = $this->taxonomyResolver->resolve($taxonomyId, $names, dryRun: true);
            }

            return $outcome->withTaxonomies($resolutions);
        }

        $product = DB::transaction(function () use ($existing, $sku, $stock, $weight, $length, $width, $height, $status, $name, $description, $price): Product {
            $product = $existing ?? new Product(['type' => ProductType::Simple->value]);
            $product->sku = $sku;

            if ($stock !== null) {
                $product->stock = $stock;
            }

            foreach (['weight' => $weight, 'length' => $length, 'width' => $width, 'height' => $height] as $attribute => $value) {
                if ($value !== null) {
                    $product->{$attribute} = $value;
                }
            }

            if ($status !== null) {
                $product->status = $status;
            } elseif ($existing === null) {
                $product->status = 'draft';
            }

            $product->save();

            $translation = [];

            if ($name !== '') {
                $translation['name'] = $name;
            }

            if ($description !== '') {
                $translation['description'] = $description;
            }

            if ($translation !== []) {
                $product->translations()->updateOrCreate(
                    ['language_id' => $this->baseLanguageId],
                    $translation,
                );
            }

            if ($price !== null && $this->defaultPriceListId > 0) {
                ProductPriceMatrix::write($product, [[
                    'price_list_id' => $this->defaultPriceListId,
                    'price' => $price,
                ]]);
            }

            return $product;
        });

        $warnings = [];

        // Taxonomy links: outside the product transaction, best-effort like the
        // images — a term not found is a report note, never a skip.
        $this->syncTaxonomies($product, $taxonomyTargets, $line, $warnings);

        // Images are fetched over HTTP, outside the row transaction, and never
        // skip the product: a failed download is a report note.
        $this->syncMainImage($product, trim($mapped['image_url'] ?? ''), $line, $warnings);
        $this->syncGallery($product, trim($mapped['gallery_urls'] ?? ''), $line, $warnings);

        return $existing !== null
            ? RowOutcome::updated($line, $warnings)
            : RowOutcome::created($line, $warnings);
    }

    /**
     * Taxonomy targets present in the row, as taxonomyId => list<termName>.
     * Empty cells are dropped (an unmapped / blank taxonomy leaves the product
     * untouched).
     *
     * @param  array<string, string>  $mapped
     * @return array<int, list<string>>
     */
    private function taxonomyTargets(array $mapped): array
    {
        $targets = [];

        foreach ($mapped as $target => $raw) {
            if (! MappingTarget::isTaxonomy($target)) {
                continue;
            }

            $names = array_values(array_filter(
                array_map('trim', explode('|', (string) $raw)),
                fn (string $name): bool => $name !== '',
            ));

            if ($names !== []) {
                $targets[MappingTarget::taxonomyId($target)] = $names;
            }
        }

        return $targets;
    }

    /**
     * @param  array<int, list<string>>  $taxonomyTargets
     * @param  list<string>  $warnings
     */
    private function syncTaxonomies(Product $product, array $taxonomyTargets, int $line, array &$warnings): void
    {
        foreach ($taxonomyTargets as $taxonomyId => $names) {
            $resolution = $this->taxonomyResolver->resolve($taxonomyId, $names, dryRun: false);

            if ($resolution->gone) {
                $warnings[] = __('pim.import.issue.taxonomy_gone', ['line' => $line]);

                continue;
            }

            $ids = $resolution->resolvedIds();

            if ($this->replaceTaxonomyTerms && $ids !== []) {
                $stale = $product->taxonomyTerms()
                    ->where('taxonomy_terms.taxonomy_id', $taxonomyId)
                    ->get()
                    ->pluck('id')
                    ->reject(fn (int $id): bool => in_array($id, $ids, true))
                    ->values()
                    ->all();

                if ($stale !== []) {
                    $product->taxonomyTerms()->detach($stale);
                }
            }

            if ($ids !== []) {
                $product->taxonomyTerms()->syncWithoutDetaching($ids);
            }

            foreach ($resolution->missingNames() as $missing) {
                $warnings[] = __('pim.import.issue.term_not_found', [
                    'line' => $line,
                    'term' => $missing,
                    'taxonomy' => $resolution->taxonomyName,
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $warnings
     */
    private function syncMainImage(Product $product, string $url, int $line, array &$warnings): void
    {
        if ($url === '') {
            return;
        }

        try {
            $image = ImageFetcher::make()->fetch($url);
        } catch (ImageFetchException $e) {
            $warnings[] = __('pim.import.issue.image_main', ['line' => $line, 'detail' => $e->getMessage()]);

            return;
        }

        $product->clearMediaCollection('main_image');
        $product->addMediaFromString($image->bytes)
            ->usingFileName($image->filename)
            ->toMediaCollection('main_image', 'public');
    }

    /**
     * @param  list<string>  $warnings
     */
    private function syncGallery(Product $product, string $raw, int $line, array &$warnings): void
    {
        if ($raw === '') {
            return;
        }

        $urls = array_values(array_filter(array_map('trim', explode('|', $raw)), fn (string $u): bool => $u !== ''));

        if ($urls === []) {
            return;
        }

        $fetcher = ImageFetcher::make();
        $product->clearMediaCollection('gallery');
        $failed = [];

        foreach ($urls as $url) {
            try {
                $image = $fetcher->fetch($url);
            } catch (ImageFetchException $e) {
                $failed[] = $e->getMessage();

                continue;
            }

            $product->addMediaFromString($image->bytes)
                ->usingFileName($image->filename)
                ->toMediaCollection('gallery', 'public');
        }

        if ($failed !== []) {
            $warnings[] = __('pim.import.issue.image_gallery', [
                'line' => $line,
                'ok' => count($urls) - count($failed),
                'total' => count($urls),
                'failed' => count($failed),
            ]);
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function number(?string $raw, string $field, int $line, array &$errors): ?float
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $value = $this->normalizeNumeric($raw);

        if ($value === null) {
            $errors[] = __('pim.import.issue.'.$field.'_not_numeric', ['line' => $line, 'value' => $raw]);

            return null;
        }

        if ($value < 0) {
            $errors[] = __('pim.import.issue.negative', [
                'line' => $line, 'field' => __('pim.import.field.'.$field), 'value' => $raw,
            ]);

            return null;
        }

        return $value;
    }

    /**
     * @param  list<string>  $errors
     */
    private function integer(?string $raw, int $line, array &$errors): ?int
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $value = $this->normalizeNumeric($raw);

        if ($value === null || $value < 0) {
            $errors[] = __('pim.import.issue.stock_not_numeric', ['line' => $line, 'value' => $raw]);

            return null;
        }

        if (fmod($value, 1.0) !== 0.0) {
            $errors[] = __('pim.import.issue.stock_not_integer', ['line' => $line, 'value' => $raw]);

            return null;
        }

        return (int) $value;
    }

    /**
     * @param  list<string>  $errors
     */
    private function status(?string $raw, int $line, array &$errors): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $key = mb_strtolower($raw);

        if (isset(self::STATUS_MAP[$key])) {
            return self::STATUS_MAP[$key];
        }

        $errors[] = __('pim.import.issue.status_unknown', ['line' => $line, 'value' => $raw]);

        return null;
    }

    private function normalizeNumeric(string $raw): ?float
    {
        $value = str_replace([' ', "\u{00A0}", "'", '€'], '', mb_strtolower($raw));
        $value = trim(preg_replace('/(kg|cm|mm|gr?)$/', '', $value) ?? $value);

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(['.', ','], ['', '.'], $value)
                : str_replace(',', '', $value);
        } elseif ($hasComma) {
            $value = str_replace(',', '.', $value);
        }

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
