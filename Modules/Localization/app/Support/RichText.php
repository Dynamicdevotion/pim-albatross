<?php

namespace Modules\Localization\Support;

/**
 * Normalizes RichEditor output for storage — shared by every translatable
 * rich-text field (product description, taxonomy/term description, …).
 */
class RichText
{
    /**
     * An empty RichEditor dehydrates to markup like `<p></p>`; treat any
     * content that has no visible text as null rather than storing markup
     * noise.
     */
    public static function normalize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = trim(strip_tags(str_replace(['&nbsp;', "\u{00A0}"], ' ', $html)));

        return $text === '' ? null : $html;
    }
}
