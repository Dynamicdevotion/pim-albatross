<?php

namespace Modules\ImportGestionali\Support;

use Modules\ImportGestionali\Enums\TargetField;
use Modules\Taxonomies\Models\Taxonomy;

/**
 * A column-mapping target is either one of the fixed {@see TargetField} values
 * or the runtime convention `taxonomy:{id}` — the parallel mechanism that lets
 * a column be mapped to a specific taxonomy without touching the (compile-time)
 * enum. The mapping json stays "column index => target string"; only the set
 * of valid targets is wider.
 */
final class MappingTarget
{
    private const TAXONOMY_PREFIX = 'taxonomy:';

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

        return __('pim.import.field.'.$target);
    }

    /**
     * Grouped option list for the mapping-step Select: "ignore", then the fixed
     * product fields, then one entry per existing taxonomy.
     *
     * @return array<string, string|array<string, string>>
     */
    public static function selectOptions(): array
    {
        $options = ['' => __('pim.import.field.ignore')];

        $fields = [];

        foreach (TargetField::cases() as $case) {
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
