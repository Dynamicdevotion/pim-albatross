<?php

namespace Modules\Products\Filament\Resources\Products\Concerns;

use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Products\Models\Product;

/**
 * Translation load/save for the variant Create/Edit actions in the
 * VariantsRelationManager. Mirrors HandlesProductTranslations (used by the
 * Product pages) but operates on a record passed into an action closure
 * instead of $this->record.
 */
trait HandlesVariantTranslations
{
    /** @var array<string, array{name?: string|null, description?: string|null, meta_title?: string|null, meta_description?: string|null}> */
    protected array $variantTranslationData = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullVariantTranslations(array $data): array
    {
        $this->variantTranslationData = $data['translations'] ?? [];
        unset($data['translations']);

        return $data;
    }

    /**
     * @return array<string, array{name: string|null, description: string|null, meta_title: string|null, meta_description: string|null}>
     */
    protected function readVariantTranslations(Product $record): array
    {
        $out = [];

        foreach ($record->translations as $translation) {
            $code = Locales::codeFor((int) $translation->language_id);

            if ($code !== null) {
                $out[$code] = [
                    'name' => $translation->name,
                    'description' => $translation->description,
                    'meta_title' => $translation->meta_title,
                    'meta_description' => $translation->meta_description,
                ];
            }
        }

        return $out;
    }

    protected function saveVariantTranslations(Product $record): void
    {
        Locales::active()->each(function (Language $language) use ($record): void {
            $row = $this->variantTranslationData[$language->code] ?? [];
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                $record->translations()->where('language_id', $language->id)->delete();

                return;
            }

            $record->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'name' => $name,
                    'description' => $this->normalizeVariantRichText($row['description'] ?? null),
                    'meta_title' => $this->nullableVariantTrim($row['meta_title'] ?? null),
                    'meta_description' => $this->nullableVariantTrim($row['meta_description'] ?? null),
                ],
            );
        });
    }

    protected function nullableVariantTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeVariantRichText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = trim(strip_tags(str_replace(['&nbsp;', "\u{00A0}"], ' ', $html)));

        return $text === '' ? null : $html;
    }
}
