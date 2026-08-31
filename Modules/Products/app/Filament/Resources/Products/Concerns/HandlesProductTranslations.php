<?php

namespace Modules\Products\Filament\Resources\Products\Concerns;

use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;

/**
 * Shared translation load/save logic for the Create and Edit product pages.
 *
 * The form keeps per-language content under the non-column `translations` key
 * (`translations.<code>.name` / `.description` / `.meta_title` /
 * `.meta_description`); these helpers move that data in and out of the
 * product_translations table (keyed by `language_id`) around the record save.
 * Only currently-active languages are touched — rows for deactivated languages
 * are left untouched ("kept hidden").
 */
trait HandlesProductTranslations
{
    /**
     * Translation form data pulled out of the record payload, keyed by code.
     *
     * @var array<string, array{name?: string|null, description?: string|null, meta_title?: string|null, meta_description?: string|null}>
     */
    protected array $translationData = [];

    /**
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillTranslations(array $data): array
    {
        foreach ($this->record->translations as $translation) {
            $code = Locales::codeFor((int) $translation->language_id);

            if ($code === null) {
                continue;
            }

            $data['translations'][$code] = [
                'name' => $translation->name,
                'description' => $translation->description,
                'meta_title' => $translation->meta_title,
                'meta_description' => $translation->meta_description,
            ];
        }

        return $data;
    }

    /**
     * Upsert one product_translations row per active language that has a name;
     * delete the row for any active language left without a name. The meta
     * fields are optional — an empty one is stored as null.
     */
    protected function saveTranslations(): void
    {
        Locales::active()->each(function (Language $language): void {
            $row = $this->translationData[$language->code] ?? [];
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                $this->record->translations()
                    ->where('language_id', $language->id)
                    ->delete();

                return;
            }

            $this->record->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'name' => $name,
                    'description' => $this->normalizeRichText($row['description'] ?? null),
                    'meta_title' => $this->nullableTrim($row['meta_title'] ?? null),
                    'meta_description' => $this->nullableTrim($row['meta_description'] ?? null),
                ],
            );
        });
    }

    protected function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
