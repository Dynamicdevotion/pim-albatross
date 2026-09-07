<?php

namespace Modules\ImportGestionali\Support;

use Modules\ImportGestionali\Enums\TargetField;
use Modules\ImportGestionali\Enums\TranslatableField;
use Modules\Localization\Models\Language;
use Modules\Localization\Support\Locales;
use Modules\Taxonomies\Models\Taxonomy;

/**
 * A column-mapping target is one of:
 *  - a fixed {@see TargetField} value;
 *  - the runtime convention `taxonomy:{id}`, for a specific taxonomy;
 *  - the runtime convention `translation:{languageId}:{field}`, for a
 *    specific active language × {@see TranslatableField}.
 *
 * Both runtime conventions exist for the same reason: they let a column map
 * to something that only exists in the database (a taxonomy, a language) —
 * mirroring each other on purpose. The mapping json stays "column index =>
 * target string"; only the set of valid targets is wider.
 *
 * `TargetField::Name`/`Description` are kept in that enum and still handled
 * by {@see ProductRowImporter} — but no
 * longer offered here in `selectOptions()`. They used to always mean "the
 * base language"; that meaning now has an explicit, equivalent entry in the
 * "Traduzioni" group (`translation:{baseLanguageId}:name`), so offering both
 * would let a file map the same product field twice through two different
 * strings, which the mapping step's own duplicate-target check can't catch
 * (it compares strings, and these two are different strings for the same
 * effective column). Keeping them alive in the importer — rather than
 * deleting the cases — means a historical `ImportRecord` saved before this
 * change, or a mapping built by anything other than this dropdown, keeps
 * working exactly as it did.
 */
final class MappingTarget
{
    private const TAXONOMY_PREFIX = 'taxonomy:';

    private const TRANSLATION_PREFIX = 'translation:';

    public static function forTaxonomy(int $taxonomyId): string
    {
        return self::TAXONOMY_PREFIX.$taxonomyId;
    }

    public static function isTaxonomy(?string $target): bool
    {
        return is_string($target) && preg_match('/^taxonomy:[1-9]\d*$/', $target) === 1;
    }

    public static function taxonomyId(string $target): int
    {
        return (int) substr($target, strlen(self::TAXONOMY_PREFIX));
    }

    public static function forTranslation(int $languageId, TranslatableField|string $field): string
    {
        $field = $field instanceof TranslatableField ? $field->value : $field;

        return self::TRANSLATION_PREFIX.$languageId.':'.$field;
    }

    public static function isTranslation(?string $target): bool
    {
        return is_string($target) && preg_match('/^translation:[1-9]\d*:[a-z_]+$/', $target) === 1;
    }

    public static function translationLanguageId(string $target): int
    {
        return (int) explode(':', $target)[1];
    }

    public static function translationField(string $target): string
    {
        return explode(':', $target)[2];
    }

    /**
     * Human label for a target, used in validation and preview messages.
     *
     * @param  array<int, string>  $taxonomyNames  id => name
     */
    public static function label(string $target, array $taxonomyNames = []): string
    {
        if (self::isTaxonomy($target)) {
            return $taxonomyNames[self::taxonomyId($target)] ?? $target;
        }

        if (self::isTranslation($target)) {
            $language = Language::find(self::translationLanguageId($target));
            $field = TranslatableField::tryFrom(self::translationField($target));

            if ($language === null || $field === null) {
                return $target;
            }

            return $field->label().' ('.$language->name.')';
        }

        return __('pim.import.field.'.$target);
    }

    /**
     * Grouped option list for the mapping-step Select: "ignore", the fixed
     * product fields (translatable ones excluded, see the class docblock),
     * then one entry per existing taxonomy, then one entry per active
     * language x translatable field.
     *
     * @return array<string, string|array<string, string>>
     */
    public static function selectOptions(): array
    {
        $options = ['' => __('pim.import.field.ignore')];

        $translatable = array_map(fn (TranslatableField $field): string => $field->value, TranslatableField::cases());

        $fields = [];

        foreach (TargetField::cases() as $case) {
            if (in_array($case->value, $translatable, true)) {
                continue;
            }

            $fields[$case->value] = $case->label();
        }

        $options[__('pim.import.group.fields')] = $fields;

        $taxonomies = self::taxonomyNames();

        if ($taxonomies !== []) {
            $group = [];

            foreach ($taxonomies as $id => $name) {
                $group[self::forTaxonomy($id)] = $name;
            }

            $options[__('pim.import.group.taxonomies')] = $group;
        }

        $translations = self::translationOptions();

        if ($translations !== []) {
            $options[__('pim.import.group.translations')] = $translations;
        }

        return $options;
    }

    /**
     * `translation:{id}:{field}` => "Field (Language)", one per active
     * language x TranslatableField, base language first.
     *
     * @return array<string, string>
     */
    public static function translationOptions(): array
    {
        $options = [];

        foreach (Locales::active() as $language) {
            foreach (TranslatableField::cases() as $field) {
                $options[self::forTranslation($language->id, $field)] = $field->label().' ('.$language->name.')';
            }
        }

        return $options;
    }

    /**
     * id => base-language name for every taxonomy, ordered by name.
     *
     * @return array<int, string>
     */
    public static function taxonomyNames(): array
    {
        return Taxonomy::query()
            ->with('translations')
            ->get()
            ->mapWithKeys(fn (Taxonomy $taxonomy): array => [$taxonomy->id => $taxonomy->name ?? $taxonomy->slug])
            ->sortBy(fn (string $name): string => mb_strtolower($name))
            ->all();
    }
}
