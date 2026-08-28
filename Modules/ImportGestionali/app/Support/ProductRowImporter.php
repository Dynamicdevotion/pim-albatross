<?php

namespace Modules\ImportGestionali\Support;

use Illuminate\Support\Facades\DB;
use Modules\Localization\Support\Locales;
use Modules\Pricing\Models\PriceList;
use Modules\Pricing\Support\ProductPriceMatrix;
use Modules\Products\Enums\ProductType;
use Modules\Products\Models\Product;

/**
 * Turns one mapped row (field => raw value) into a created/updated/skipped
 * outcome for a simple product. Matching is by SKU. On update, an empty or
 * unmapped value leaves the existing value untouched.
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
    ) {}

    public static function make(): self
    {
        return new self(
            (int) (PriceList::default()?->id ?? 0),
            (int) Locales::base()->id,
        );
    }

    /**
     * @param  array<string, string>  $mapped  field => raw value
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

        if ($dryRun) {
            return $existing !== null ? RowOutcome::updated($line) : RowOutcome::created($line);
        }

        DB::transaction(function () use ($existing, $sku, $stock, $weight, $length, $width, $height, $status, $name, $description, $price): void {
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
        });

        return $existing !== null ? RowOutcome::updated($line) : RowOutcome::created($line);
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
