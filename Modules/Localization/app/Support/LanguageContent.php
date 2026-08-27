<?php

namespace Modules\Localization\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Localization\Models\Language;

/**
 * Knows every translation table that references `languages.id`. Used by the
 * language deactivation flow to detect and optionally purge content in a
 * language. Tables are checked for existence so this stays valid while the
 * per-module translation tables are still being rolled out.
 */
class LanguageContent
{
    /**
     * @var list<string>
     */
    public const TABLES = [
        'product_translations',
        'taxonomy_translations',
        'taxonomy_term_translations',
    ];

    public static function has(Language $language): bool
    {
        foreach (static::tables() as $table) {
            if (DB::table($table)->where('language_id', $language->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete every translation row in this language. Returns the row count.
     */
    public static function purge(Language $language): int
    {
        $deleted = 0;

        foreach (static::tables() as $table) {
            $deleted += DB::table($table)->where('language_id', $language->getKey())->delete();
        }

        return $deleted;
    }

    /**
     * @return list<string>
     */
    private static function tables(): array
    {
        return array_values(array_filter(
            static::TABLES,
            fn (string $table): bool => Schema::hasTable($table)
                && Schema::hasColumn($table, 'language_id'),
        ));
    }
}
