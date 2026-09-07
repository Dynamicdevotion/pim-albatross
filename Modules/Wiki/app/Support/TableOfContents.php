<?php

namespace Modules\Wiki\Support;

use DOMDocument;
use DOMXPath;

/**
 * Builds the right-hand "on this page" summary from a page's already
 * rendered HTML, by reading the `id` attributes {@see MarkdownRenderer}
 * already put on every `<h2>`/`<h3>` via CommonMark's HeadingPermalink
 * extension. Walking the rendered DOM (native `ext-dom`, no new dependency)
 * keeps the summary fully decoupled from where it's displayed — CommonMark's
 * own TableOfContentsExtension only knows how to inject a TOC inline into
 * the document flow, which isn't what a separate sidebar column needs.
 */
final class TableOfContents
{
    /**
     * @return list<array{level: int, id: string, text: string}>
     */
    public static function extract(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument;

        $previousUseErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        $xpath = new DOMXPath($document);
        $entries = [];

        foreach ($xpath->query('//*[self::h2 or self::h3]') as $heading) {
            $id = $heading->attributes?->getNamedItem('id')?->nodeValue;

            if (! is_string($id) || $id === '') {
                continue;
            }

            // The heading's own text, excluding the "¶" permalink link
            // MarkdownRenderer appends inside it — that's a hover affordance
            // for copying the anchor, not part of the heading's title.
            foreach ($xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " wiki-heading-permalink ")]', $heading) as $permalink) {
                $permalink->parentNode?->removeChild($permalink);
            }

            $entries[] = [
                'level' => (int) substr($heading->nodeName, 1),
                'id' => $id,
                'text' => trim($heading->textContent),
            ];
        }

        return $entries;
    }
}
