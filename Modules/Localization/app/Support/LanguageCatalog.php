<?php

namespace Modules\Localization\Support;

/**
 * The languages the PIM ships knowledge of. `it/en/es/fr/de` start active (they
 * were already in use); `it` is the base language. Everything else starts
 * inactive and can be switched on from the admin panel.
 *
 * Single source of truth for both the create_languages_table migration and
 * LanguageSeeder.
 */
class LanguageCatalog
{
    private const ACTIVE = ['it', 'en', 'es', 'fr', 'de'];

    private const NAMES = [
        'it' => 'Italiano',
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'pt' => 'Português',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'sv' => 'Svenska',
        'da' => 'Dansk',
        'fi' => 'Suomi',
        'no' => 'Norsk',
        'cs' => 'Čeština',
        'el' => 'Ελληνικά',
        'hu' => 'Magyar',
        'ro' => 'Română',
        'ru' => 'Русский',
        'tr' => 'Türkçe',
        'ja' => '日本語',
        'zh' => '中文',
        'ar' => 'العربية',
    ];

    /**
     * @return list<array{code: string, name: string, active: bool, is_base: bool}>
     */
    public static function all(): array
    {
        $rows = [];

        foreach (self::NAMES as $code => $name) {
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'active' => in_array($code, self::ACTIVE, true),
                'is_base' => $code === 'it',
            ];
        }

        return $rows;
    }
}
