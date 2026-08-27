<?php

namespace Modules\Localization\Enums;

/**
 * Content locales supported by the PIM.
 *
 * This is the single source of truth for which languages product content can be
 * translated into. It is independent from APP_LOCALE, which only drives the
 * admin UI language.
 */
enum Locale: string
{
    case Italian = 'it';
    case English = 'en';
    case Spanish = 'es';
    case French = 'fr';
    case German = 'de';

    /**
     * The base / default content locale. Always required on a product.
     */
    public static function default(): self
    {
        return self::Italian;
    }

    public function isDefault(): bool
    {
        return $this === self::default();
    }

    /**
     * All locale codes.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $locale): string => $locale->value, self::cases());
    }

    /**
     * Native language name, used for UI labels.
     */
    public function label(): string
    {
        return match ($this) {
            self::Italian => 'Italiano',
            self::English => 'English',
            self::Spanish => 'Español',
            self::French => 'Français',
            self::German => 'Deutsch',
        };
    }
}
