<?php

namespace Modules\Products\Filament\Resources\Products\Concerns;

use Modules\Localization\Enums\Locale;

/**
 * Shared translation load/save logic for the Create and Edit product pages.
 *
 * The form keeps per-locale content under the non-column `translations` key
 * (`translations.<locale>.name` / `.description`); these helpers move that data
 * in and out of the product_translations table around the record save.
 */
trait HandlesProductTranslations
{
    /**
     * Translation form data pulled out of the record payload, keyed by locale.
     *
     * @var array<string, array{name?: string|null, description?: string|null}>
     */
    protected array $translationData = [];

    /**
     * Remove the non-column `translations` key from the data written to the
     * products table, keeping it for saveTranslations().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractTranslations(array $data): array
    {
        $this->translationData = $data['translations'] ?? [];
        unset($data['translations']);

        return $data;
    }

    /**
     * Load existing translations into the `translations.<locale>.*` form state.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillTranslations(array $data): array
    {
        foreach ($this->record->translations as $translation) {
            $data['translations'][$translation->locale] = [
                'name' => $translation->name,
                'description' => $translation->description,
            ];
        }

        return $data;
    }

    /**
     * Upsert one product_translations row per locale that has a name; delete
     * the row for any locale left without a name (name is NOT NULL).
     */
    protected function saveTranslations(): void
    {
        foreach (Locale::cases() as $locale) {
            $row = $this->translationData[$locale->value] ?? [];
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                $this->record->translations()
                    ->where('locale', $locale->value)
                    ->delete();

                continue;
            }

            $this->record->translations()->updateOrCreate(
                ['locale' => $locale->value],
                [
                    'name' => $name,
                    'description' => $this->normalizeRichText($row['description'] ?? null),
                ],
            );
        }
    }

    /**
     * An empty RichEditor dehydrates to markup like `<p></p>`; treat any
     * content that has no visible text as null.
     */
    protected function normalizeRichText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = trim(strip_tags(str_replace(['&nbsp;', "\u{00A0}"], ' ', $html)));

        return $text === '' ? null : $html;
    }
}
