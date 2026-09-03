<?php

namespace Modules\Localization\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Localization\Support\RichText;
use Modules\Localization\Support\SlugGenerator;

/**
 * Tab-per-language editing of a translatable `name` + `slug` + `description`
 * stored in a related `translations` table (keyed by `language_id`), plus
 * generation of a *separate*, non-translated `slug` on the parent record
 * itself (e.g. `taxonomies.slug`) from the base-language name.
 *
 * These are two distinct slugs, both handled by this trait:
 *  - the parent-record one (`slugModelClass()` + co.) is the stable internal
 *    identifier WooSync/ImportGestionali already look up by — untouched,
 *    still one per record, still derived only from the base-language name;
 *  - the per-translation one (`translations.<code>.slug`) is the new
 *    user-facing, per-language slug, unique within its own language (and
 *    whatever extra scope `scopeTranslationSlugQuery()` adds, e.g. "same
 *    taxonomy" for terms).
 *
 * The using class must implement slugModelClass(); it may override
 * slugScope(), slugExistingKey() and scopeTranslationSlugQuery().
 */
trait HandlesTranslatableName
{
    /**
     * @var array<string, array{name?: string|null, slug?: string|null, description?: string|null}>
     */
    protected array $nameTranslations = [];

    /**
     * Pull the non-column `translations` payload out of the record data and,
     * when the parent record's own `slug` is blank, derive it from the
     * base-language name.
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
                $data['slug'] = $this->uniqueSlug($base);
            }
        }

        return $data;
    }

    /**
     * Build the `translations.<code>.{name,slug,description}` form state from
     * an existing record.
     *
     * @return array<string, array{name: string, slug: ?string, description: ?string}>
     */
    protected function nameTranslationsFor(Model $record): array
    {
        $out = [];

        foreach ($record->translations as $translation) {
            $code = Locales::codeFor((int) $translation->language_id);

            if ($code !== null) {
                $out[$code] = [
                    'name' => $translation->name,
                    'slug' => $translation->slug,
                    'description' => $translation->description,
                ];
            }
        }

        return $out;
    }

    /**
     * Upsert one translation row per active language that has a name; delete
     * the row for any active language left blank. A blank per-language slug
     * is generated from that language's name; a submitted one is sanitized
     * and used as-is.
     */
    protected function saveNameTranslations(Model $record): void
    {
        Locales::active()->each(function (Language $language) use ($record): void {
            $row = $this->nameTranslations[$language->code] ?? [];
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                $record->translations()->where('language_id', $language->id)->delete();

                return;
            }

            $record->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'name' => $name,
                    'slug' => $this->resolveTranslatedSlug($record, $row['slug'] ?? null, $name, $language),
                    'description' => RichText::normalize($row['description'] ?? null),
                ],
            );
        });
    }

    /**
     * @return class-string<Model>
     */
    abstract protected function slugModelClass(): string;

    /**
     * Extra where-clauses scoping the *parent record's own* slug uniqueness.
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

    /**
     * The parent record's own stable slug — unchanged behaviour, now
     * delegating its dedup loop to the shared SlugGenerator.
     */
    protected function uniqueSlug(string $base): string
    {
        $class = $this->slugModelClass();

        return SlugGenerator::unique($base, fn (string $candidate): bool => $class::query()
            ->where('slug', $candidate)
            ->where($this->slugScope())
            ->when(
                $this->slugExistingKey(),
                fn ($query, $key) => $query->whereKeyNot($key),
            )
            ->exists());
    }

    /**
     * Extra constraints scoping the *per-translation* slug's uniqueness
     * beyond "same language, different record" — e.g. "same taxonomy" for
     * terms. No-op by default (global per language, like Taxonomy's own).
     */
    protected function scopeTranslationSlugQuery(Builder $query): Builder
    {
        return $query;
    }

    /**
     * The submitted per-language slug (sanitized), or one derived from that
     * language's name when left blank — always unique within the scope
     * `scopeTranslationSlugQuery()` defines. The translation model class and
     * its owning foreign key are read straight off the `translations`
     * relation, so no extra contract method is needed for those.
     */
    protected function resolveTranslatedSlug(Model $record, ?string $submitted, string $name, Language $language): string
    {
        $relation = $record->translations();
        $translationClass = get_class($relation->getRelated());
        $foreignKey = $relation->getForeignKeyName();

        $submitted = trim((string) $submitted);
        $base = $submitted !== '' ? $submitted : $name;

        return SlugGenerator::unique(
            $base,
            fn (string $candidate): bool => $this->scopeTranslationSlugQuery(
                $translationClass::query()
                    ->where('language_id', $language->id)
                    ->where('slug', $candidate)
                    ->where($foreignKey, '!=', $record->getKey())
            )->exists(),
        );
    }
}
