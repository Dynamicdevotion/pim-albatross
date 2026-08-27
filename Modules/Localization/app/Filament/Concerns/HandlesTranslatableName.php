<?php

namespace Modules\Localization\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;

/**
 * Tab-per-language editing of a single translatable `name` stored in a related
 * `translations` table (keyed by `language_id`), plus slug generation from the
 * base-language name.
 *
 * The using class must implement slugModelClass(); it may override slugScope()
 * and slugExistingKey().
 */
trait HandlesTranslatableName
{
    /**
     * @var array<string, array{name?: string|null}>
     */
    protected array $nameTranslations = [];

    /**
     * Pull the non-column `translations` payload out of the record data and,
     * when `slug` is blank, derive it from the base-language name.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractNameTranslations(array $data): array
    {
        $this->nameTranslations = $data['translations'] ?? [];
        unset($data['translations']);

        if (blank($data['slug'] ?? null)) {
            $base = trim((string) ($this->nameTranslations[Locales::baseCode()]['name'] ?? ''));

            if ($base !== '') {
                $data['slug'] = $this->uniqueSlug(Str::slug($base));
            }
        }

        return $data;
    }

    /**
     * Build the `translations.<code>.name` form state from an existing record.
     *
     * @return array<string, array{name: string}>
     */
    protected function nameTranslationsFor(Model $record): array
    {
        $out = [];

        foreach ($record->translations as $translation) {
            $code = Locales::codeFor((int) $translation->language_id);

            if ($code !== null) {
                $out[$code] = ['name' => $translation->name];
            }
        }

        return $out;
    }

    /**
     * Upsert one translation row per active language that has a name; delete the
     * row for any active language left blank.
     */
    protected function saveNameTranslations(Model $record): void
    {
        Locales::active()->each(function (Language $language) use ($record): void {
            $name = trim((string) ($this->nameTranslations[$language->code]['name'] ?? ''));

            if ($name === '') {
                $record->translations()->where('language_id', $language->id)->delete();

                return;
            }

            $record->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['name' => $name],
            );
        });
    }

    /**
     * @return class-string<Model>
     */
    abstract protected function slugModelClass(): string;

    /**
     * Extra where-clauses scoping slug uniqueness.
     *
     * @return array<string, mixed>
     */
    protected function slugScope(): array
    {
        return [];
    }

    protected function slugExistingKey(): int|string|null
    {
        return null;
    }

    protected function uniqueSlug(string $base): string
    {
        $class = $this->slugModelClass();
        $base = $base ?: 'item';
        $slug = $base;
        $suffix = 2;

        while (
            $class::query()
                ->where('slug', $slug)
                ->where($this->slugScope())
                ->when(
                    $this->slugExistingKey(),
                    fn ($query, $key) => $query->whereKeyNot($key),
                )
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
