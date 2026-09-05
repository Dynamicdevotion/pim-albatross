<?php

namespace Modules\ImportGestionali\Support;

/**
 * First pass of the variant-aware import: a pure, query-free classification of
 * every buffered row.
 *
 * The order of the rows in the file carries no meaning — a variant may sit
 * above the row that defines its parent, or the parent may have no row of its
 * own at all. The only link between a variant and its container is the SKU in
 * the "parent_sku" column.
 *
 * Each row is one of:
 *  - {@see SIMPLE}    — `parent_sku` empty and no other row names this SKU as a parent;
 *  - {@see CONTAINER} — `parent_sku` empty but at least one other row points at this SKU;
 *  - {@see VARIANT}   — `parent_sku` filled.
 */
final class VariantImportPlan
{
    public const SIMPLE = 'simple';

    public const CONTAINER = 'container';

    public const VARIANT = 'variant';

    /**
     * @param  array<int, string>  $classification      line => SIMPLE|CONTAINER|VARIANT
     * @param  list<int>  $topLevelLines                lines with an empty `parent_sku`, in file order
     * @param  list<int>  $variantLines                 lines with a filled `parent_sku`, in file order
     * @param  array<int, string>  $parentKeyByLine     variant line => lower-cased parent SKU
     * @param  array<int, string>  $containerKeyByLine  container line => its own lower-cased SKU
     * @param  array<string, string>  $referencedParents  lower-cased parent SKU => SKU as first written
     * @param  array<string, int>  $variantSkus         lower-cased SKU of a variant row => that line
     * @param  array<int, int>  $duplicateTopLevel      2nd+ top-level line sharing a SKU => the first line
     */
    private function __construct(
        public readonly array $classification,
        public readonly array $topLevelLines,
        public readonly array $variantLines,
        public readonly array $parentKeyByLine,
        public readonly array $containerKeyByLine,
        public readonly array $referencedParents,
        public readonly array $variantSkus,
        public readonly array $duplicateTopLevel,
    ) {}

    /**
     * @param  array<int, array<string, string>>  $rows  line => mapped row (target => value)
     */
    public static function build(array $rows): self
    {
        $topLevelLines = [];
        $variantLines = [];
        $parentKeyByLine = [];
        $referencedParents = [];
        $variantSkus = [];
        $skuByLine = [];

        foreach ($rows as $line => $mapped) {
            $sku = self::lower($mapped['sku'] ?? '');
            $parent = trim((string) ($mapped['parent_sku'] ?? ''));
            $skuByLine[$line] = $sku;

            if ($parent === '') {
                $topLevelLines[] = $line;

                continue;
            }

            $variantLines[] = $line;
            $key = self::lower($parent);
            $parentKeyByLine[$line] = $key;
            $referencedParents[$key] ??= $parent;

            if ($sku !== '') {
                $variantSkus[$sku] ??= $line;
            }
        }

        $classification = [];
        $containerKeyByLine = [];
        $firstTopLevelLineForSku = [];
        $duplicateTopLevel = [];

        foreach ($topLevelLines as $line) {
            $sku = $skuByLine[$line];

            if ($sku !== '') {
                if (isset($firstTopLevelLineForSku[$sku])) {
                    $duplicateTopLevel[$line] = $firstTopLevelLineForSku[$sku];
                } else {
                    $firstTopLevelLineForSku[$sku] = $line;
                }
            }

            if ($sku !== '' && isset($referencedParents[$sku])) {
                $classification[$line] = self::CONTAINER;
                $containerKeyByLine[$line] = $sku;
            } else {
                $classification[$line] = self::SIMPLE;
            }
        }

        foreach ($variantLines as $line) {
            $classification[$line] = self::VARIANT;
        }

        return new self(
            $classification,
            $topLevelLines,
            $variantLines,
            $parentKeyByLine,
            $containerKeyByLine,
            $referencedParents,
            $variantSkus,
            $duplicateTopLevel,
        );
    }

    /**
     * Parent SKUs referenced by a variant but never defined by a top-level row
     * of their own — they must resolve to an already existing product or their
     * variants cannot be imported.
     *
     * @return array<string, string>  lower-cased SKU => SKU as first written
     */
    public function impliedParents(): array
    {
        return array_diff_key($this->referencedParents, array_flip($this->containerKeyByLine));
    }

    private static function lower(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
