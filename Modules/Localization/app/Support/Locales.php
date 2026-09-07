<?php

namespace Modules\Localization\Support;

use Illuminate\Support\Collection;
use Modules\Localization\Models\Language;

/**
 * Read facade over the `languages` table — the replacement for the old
 * Locale enum. Content code keys translations by locale *code*; the database
 * stores `language_id`, so idFor()/codeFor() bridge the two.
 */
class Locales
{
    /**
     * Active languages, base first — collapsed to the base language alone
     * when {@see MultilanguageFeature} is off, regardless of how many other
     * languages are marked `active` in the database. This is the single seam
     * every translation tab, filter, export and sync integration already
     * reads through, so every one of them automatically respects the flag
     * without needing its own check. Because only this read path is gated
     * (never the `active` column itself), no data is touched: turning the
     * flag back on makes every previously-active language reappear exactly
     * as it was.
     *
     * @return Collection<int, Language>
     */
    public static function active(): Collection
    {
        $query = Language::query()->active()->orderByDesc('is_base')->orderBy('name');

        if (! MultilanguageFeature::enabled()) {
            $query->where('is_base', true);
        }

        return $query->get();
    }

    /**
     * @return list<string>
     */
    public static function activeCodes(): array
    {
        return static::active()->pluck('code')->all();
    }

    public static function base(): Language
    {
        return Language::query()->where('is_base', true)->firstOrFail();
    }

    public static function baseCode(): string
    {
        return static::base()->code;
    }

    public static function idFor(string $code): ?int
    {
        return static::map()[$code] ?? null;
    }

    public static function codeFor(int $id): ?string
    {
        $code = array_search($id, static::map(), true);

        return $code === false ? null : $code;
    }

    /**
     * @return array<string, int>
     */
    private static function map(): array
    {
        return Language::query()->pluck('id', 'code')->all();
    }
}
