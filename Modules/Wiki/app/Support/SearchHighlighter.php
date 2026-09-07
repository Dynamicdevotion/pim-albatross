<?php

namespace Modules\Wiki\Support;

/**
 * Turns a plain-text string plus the current search term into safe HTML with
 * the matched term wrapped in `<mark>`. Escaping happens here (not by the
 * caller) because both the source text and the snippet extracted from it are
 * developer-authored Markdown, but the search term itself is user input —
 * `preg_quote` plus escaping the haystack first keeps this XSS-safe without
 * needing a templating helper.
 */
final class SearchHighlighter
{
    public static function highlight(string $text, string $needle): string
    {
        $escaped = e($text);
        $needle = trim($needle);

        if ($needle === '') {
            return $escaped;
        }

        $pattern = '/'.preg_quote(e($needle), '/').'/iu';

        return preg_replace($pattern, '<mark>$0</mark>', $escaped) ?? $escaped;
    }

    /**
     * A short excerpt of $text centered on the first match of $needle, or
     * null when $needle doesn't occur (e.g. it only matched the title).
     */
    public static function snippet(string $text, string $needle, int $radius = 60): ?string
    {
        $needle = trim($needle);

        if ($needle === '') {
            return null;
        }

        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
        $position = mb_stripos($plain, $needle);

        if ($position === false) {
            return null;
        }

        $start = max(0, $position - $radius);
        $length = mb_strlen($needle) + ($radius * 2);
        $excerpt = mb_substr($plain, $start, $length);

        $prefix = $start > 0 ? '…' : '';
        $suffix = ($start + $length) < mb_strlen($plain) ? '…' : '';

        return $prefix.$excerpt.$suffix;
    }
}
