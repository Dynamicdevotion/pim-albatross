<?php

namespace Modules\ImportGestionali\Support;

use Illuminate\Support\Facades\DB;
use Modules\Localization\Filament\Concerns\HandlesTranslatableName;
use Modules\Localization\Models\ProductTranslation;
use Modules\Localization\Support\Locales;
use Modules\Localization\Support\SlugGenerator;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Support\ProductPriceMatrix;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;
use Modules\Products\Support\VariantGenerator;

/**
 * Turns one mapped row (target => raw value) into a created/updated/skipped
 * outcome. Matching is by SKU. On update, an empty or unmapped value leaves
 * the existing value untouched.
 *
 * {@see import()} handles a plain simple product; {@see importParent()} and
 * {@see importVariant()} handle the two-pass variable-product path driven by
 * the "Codice Padre" column (see {@see ImportRunner} and {@see VariantImportPlan}).
 *
 * Columns mapped to a taxonomy (`taxonomy:{id}`, see {@see MappingTarget}) hold
 * one or more term names separated by `|`; they are resolved against that
 * taxonomy and linked through `product_taxonomy_term` — the same mechanism for
 * a simple product, a container and a variant.
 *
 * Columns mapped to a translation (`translation:{languageId}:{field}`, also
 * {@see MappingTarget}) are collected by {@see translationTargets()} and
 * written by {@see writeTranslations()}, one `product_translations` row per
 * language present in the row — see those methods for the exact rules
 * (blank cell never touches an existing value, slug generation, a non-base
 * language needs a name to be created at all). The legacy bare `name`/
 * `description` targets — no longer offered by the mapping step's dropdown,
 * see {@see MappingTarget} — are folded into the base language by the same
 * method, so a historical `ImportRecord` mapped before this feature existed
 * keeps working unchanged.
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

        $targets = $this->translationTargets($mapped);
        $baseName = trim($targets[$this->baseLanguageId]['name'] ?? '');

        // In the preview a row with a "Codice Padre" value is a variant: it may
        // legitimately have no name of its own (it inherits the parent's), so
        // the "name required" rule does not apply. The real run never routes a
        // variant row through import() — see ImportRunner.
        $isVariantRow = trim($mapped['parent_sku'] ?? '') !== '';

        if ($existing === null && $baseName === '' && ! $isVariantRow) {
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

        $product = DB::transaction(function () use ($existing, $sku, $stock, $weight, $length, $width, $height, $status, $targets, $price): Product {
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

            $this->writeTranslations($product, $targets);

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
     * Create or update the variable container for a group of variants.
     *
     * `$definitionRow` is true when the file has a row of its own for this SKU
     * (its cells seed / update the container); false when the parent is only
     * implied by its variant rows, in which case it must already exist as a
     * product or the group cannot be built.
     *
     * The returned outcome carries the container id in `productId` on every
     * non-skip result — including {@see RowOutcome::UNCHANGED}, an existing
     * container reused untouched (toggle off) that the variants still attach to.
     *
     * A container never carries its own price, stock or dimensions: those
     * cells are ignored here. When an existing `simple` product is converted,
     * its `stock`/dimensions are nulled by the model's `saving` hook and its
     * price-list rows are deleted, since neither is reachable on a container.
     *
     * @param  array<string, string>  $mapped
     * @param  array<string, int>  $seenSkus  lower-cased sku => the line it first appeared on
     */
    public function importParent(array $mapped, int $line, bool $definitionRow, bool $updateExisting, array &$seenSkus): RowOutcome
    {
        $sku = trim($mapped['sku'] ?? '');

        if ($sku === '') {
            return RowOutcome::skipped($line, __('pim.import.issue.sku_missing', ['line' => $line]));
        }

        $key = mb_strtolower($sku);

        if ($definitionRow) {
            if (isset($seenSkus[$key])) {
                return RowOutcome::skipped($line, __('pim.import.issue.sku_dup_in_file', [
                    'line' => $line, 'sku' => $sku, 'first' => $seenSkus[$key],
                ]));
            }

            $seenSkus[$key] = $line;
        }

        $existing = Product::query()->where('sku', $sku)->first();
        $targets = $this->translationTargets($mapped);
        $baseName = trim($targets[$this->baseLanguageId]['name'] ?? '');

        if ($existing === null) {
            if (! $definitionRow) {
                return RowOutcome::skipped($line, __('pim.import.issue.parent_not_found', ['line' => $line, 'sku' => $sku]), 'parent_not_found');
            }

            if ($baseName === '') {
                return RowOutcome::skipped($line, __('pim.import.issue.name_missing', ['line' => $line]));
            }

            $warnings = [];
            $product = DB::transaction(function () use ($sku, $mapped, $targets, $line, &$warnings): Product {
                $product = new Product(['type' => ProductType::Variable->value]);
                $product->sku = $sku;
                $this->applyContainerFields($product, $mapped, $targets, $line, $warnings);

                return $product;
            });

            $this->syncContainerRelations($product, $mapped, $line, $warnings);

            return RowOutcome::created($line, $warnings)->withProductId($product->id);
        }

        if ($existing->isVariant()) {
            return RowOutcome::skipped($line, __('pim.import.issue.parent_is_variant', ['line' => $line, 'sku' => $sku]), 'parent_is_variant');
        }

        if ($existing->isSimple()) {
            if (! $updateExisting) {
                return RowOutcome::skipped($line, __('pim.import.issue.parent_exists_update_off', ['line' => $line, 'sku' => $sku]), 'parent_exists_update_off');
            }

            $warnings = [__('pim.import.issue.parent_converted', ['line' => $line, 'sku' => $sku])];
            $product = DB::transaction(function () use ($existing, $mapped, $targets, $line, &$warnings): Product {
                $existing->type = ProductType::Variable;
                $existing->save();              // saving hook nulls stock + dimensions
                $existing->prices()->delete();  // a container has no own price in the UI
                $this->applyContainerFields($existing, $mapped, $targets, $line, $warnings);

                return $existing;
            });

            $this->syncContainerRelations($product, $mapped, $line, $warnings);

            return RowOutcome::updated($line, $warnings)->withProductId($product->id);
        }

        // Already a variable container.
        if ($definitionRow && $updateExisting) {
            $warnings = [];
            $product = DB::transaction(function () use ($existing, $mapped, $targets, $line, &$warnings): Product {
                $this->applyContainerFields($existing, $mapped, $targets, $line, $warnings);

                return $existing;
            });

            $this->syncContainerRelations($product, $mapped, $line, $warnings);

            return RowOutcome::updated($line, $warnings)->withProductId($product->id);
        }

        return RowOutcome::unchanged($line, $existing->id);
    }

    /**
     * Create or update one variant row under an already-persisted variable
     * container ($parentId). Mirrors {@see import()} but writes a `variant`
     * with its own price/stock/dimensions. When the row carries no
     * translation data of its own at all, it inherits every one of the
     * parent's translations instead — exactly like the admin's "Generate
     * variants" — rather than mixing inherited and row-supplied content
     * language by language.
     *
     * @param  array<string, string>  $mapped
     * @param  array<string, int>  $seenSkus
     */
    public function importVariant(array $mapped, int $line, int $parentId, bool $updateExisting, array &$seenSkus): RowOutcome
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

        if ($existing !== null && ! $existing->isVariant()) {
            return RowOutcome::skipped($line, __('pim.import.issue.variant_sku_conflict', ['line' => $line, 'sku' => $sku]));
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

        $targets = $this->translationTargets($mapped);
        $baseName = trim($targets[$this->baseLanguageId]['name'] ?? '');
        $taxonomyTargets = $this->taxonomyTargets($mapped);
        $isNew = $existing === null;

        $product = DB::transaction(function () use ($existing, $isNew, $parentId, $sku, $stock, $weight, $length, $width, $height, $status, $targets, $price): Product {
            $product = $existing ?? new Product(['type' => ProductType::Variant->value, 'parent_id' => $parentId]);
            $product->parent_id = $parentId;
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
            } elseif ($isNew) {
                $product->status = 'draft';
            }

            $product->save();

            if ($targets !== []) {
                $this->writeTranslations($product, $targets);
            } elseif ($isNew) {
                $parent = Product::query()->with('translations')->find($parentId);

                if ($parent !== null) {
                    VariantGenerator::copyTranslations($parent, $product, Locales::baseCode(), null);
                }
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
        $this->syncTaxonomies($product, $taxonomyTargets, $line, $warnings);
        $this->syncMainImage($product, trim($mapped['image_url'] ?? ''), $line, $warnings);
        $this->syncGallery($product, trim($mapped['gallery_urls'] ?? ''), $line, $warnings);

        if ($isNew && $baseName === '' && $product->translate(Locales::baseCode())?->name === null) {
            $warnings[] = __('pim.import.issue.variant_name_missing', ['line' => $line, 'sku' => $sku]);
        }

        return $isNew
            ? RowOutcome::created($line, $warnings)
            : RowOutcome::updated($line, $warnings);
    }

    /**
     * Container-level scalar cells: status, plus whatever translations the
     * row carries. Price, stock and dimensions are deliberately not touched.
     * Must run inside a DB transaction opened by the caller; an unrecognised
     * status is a report note here, never a skip.
     *
     * @param  array<string, string>  $mapped
     * @param  array<int, array<string, string>>  $targets  from translationTargets()
     * @param  list<string>  $warnings
     */
    private function applyContainerFields(Product $product, array $mapped, array $targets, int $line, array &$warnings): void
    {
        $errors = [];
        $status = $this->status($mapped['status'] ?? null, $line, $errors);

        foreach ($errors as $error) {
            $warnings[] = $error;
        }

        if ($status !== null) {
            $product->status = $status;
        } elseif (! $product->exists) {
            $product->status = 'draft';
        }

        $product->save();

        $this->writeTranslations($product, $targets);
    }

    /**
     * Container taxonomy links and images — best-effort, outside the row
     * transaction, exactly like the simple-product path.
     *
     * @param  array<string, string>  $mapped
     * @param  list<string>  $warnings
     */
    private function syncContainerRelations(Product $product, array $mapped, int $line, array &$warnings): void
    {
        $this->syncTaxonomies($product, $this->taxonomyTargets($mapped), $line, $warnings);
        $this->syncMainImage($product, trim($mapped['image_url'] ?? ''), $line, $warnings);
        $this->syncGallery($product, trim($mapped['gallery_urls'] ?? ''), $line, $warnings);
    }

    /**
     * Translation targets present in the row, as languageId => [field =>
     * value]. Empty cells are dropped per field — an unmapped/blank cell for
     * a given language+field leaves the existing value untouched on update,
     * see {@see writeTranslations()}.
     *
     * The legacy bare `name`/`description` targets (no longer offered by the
     * mapping step, see {@see MappingTarget}) are folded in here as aliases
     * for the base language, so a historical `ImportRecord` mapped before
     * the "Traduzioni" group existed keeps working unchanged. A file cannot
     * produce both through today's dropdown, so there is no real ambiguity
     * about which one wins when both happen to be present.
     *
     * @param  array<string, string>  $mapped
     * @return array<int, array<string, string>>
     */
    private function translationTargets(array $mapped): array
    {
        $targets = [];

        $legacyName = trim($mapped['name'] ?? '');

        if ($legacyName !== '') {
            $targets[$this->baseLanguageId]['name'] = $legacyName;
        }

        $legacyDescription = trim($mapped['description'] ?? '');

        if ($legacyDescription !== '') {
            $targets[$this->baseLanguageId]['description'] = $legacyDescription;
        }

        foreach ($mapped as $target => $raw) {
            if (! MappingTarget::isTranslation($target)) {
                continue;
            }

            $value = trim((string) $raw);

            if ($value === '') {
                continue;
            }

            $targets[MappingTarget::translationLanguageId($target)][MappingTarget::translationField($target)] = $value;
        }

        return $targets;
    }

    /**
     * Upsert one `product_translations` row per language present in
     * $targets — only the fields actually given for that language are
     * touched, so a blank/unmapped cell never overwrites an existing value.
     *
     * A brand-new translation for a language other than the base one needs a
     * name to be created at all, mirroring
     * {@see HandlesTranslatableName::saveNameTranslations()}
     * in the admin form (a translation without a name is simply not
     * created); the base language keeps its existing behaviour unchanged,
     * since the base name is already guaranteed present by the caller
     * before a *new* product is ever created (an update may legitimately
     * touch only a non-name field on the base language).
     *
     * Slug: generated from the name when a brand-new translation leaves it
     * blank; a submitted slug (create or update) is sanitized and checked
     * unique within that language, excluding this product's own row — the
     * same rule the admin form's per-language slug field follows.
     *
     * @param  array<int, array<string, string>>  $targets  languageId => field => value
     */
    private function writeTranslations(Product $product, array $targets): void
    {
        foreach ($targets as $languageId => $fields) {
            $existing = $product->exists
                ? $product->translations()->where('language_id', $languageId)->first()
                : null;

            if ($existing === null && $languageId !== $this->baseLanguageId && ($fields['name'] ?? '') === '') {
                continue;
            }

            if (array_key_exists('slug', $fields) || $existing === null) {
                $name = $fields['name'] ?? $existing?->name ?? '';
                $fields['slug'] = $this->resolveImportSlug($product, $languageId, $fields['slug'] ?? null, $name);
            }

            $product->translations()->updateOrCreate(['language_id' => $languageId], $fields);
        }
    }

    /**
     * A submitted slug, sanitized; or one derived from $name when left
     * blank. Always unique within $languageId, excluding this product's own
     * translation row — mirrors
     * {@see HandlesTranslatableName::resolveTranslatedSlug()}.
     */
    private function resolveImportSlug(Product $product, int $languageId, ?string $submitted, string $name): string
    {
        $submitted = trim((string) $submitted);
        $base = $submitted !== '' ? $submitted : $name;

        return SlugGenerator::unique(
            $base,
            fn (string $candidate): bool => ProductTranslation::query()
                ->where('language_id', $languageId)
                ->where('slug', $candidate)
                ->where('product_id', '!=', $product->id ?? 0)
                ->exists(),
        );
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
