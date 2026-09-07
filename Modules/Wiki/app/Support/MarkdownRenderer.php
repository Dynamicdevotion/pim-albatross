<?php

namespace Modules\Wiki\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Wraps CommonMark with the GFM extensions the docs actually use (tables,
 * bare-URL autolinking, stable heading anchors) — all three ship inside the
 * `league/commonmark` package already pulled in transitively by Laravel, no
 * new dependency needed. `html_input` is left at "allow" because docs/*.md is
 * developer-authored and versioned in git, not user-submitted content.
 *
 * Heading anchors use the raw slug as both the `id` (put directly on the
 * `<h2>`/`<h3>` via `apply_id_to_heading`) and the permalink's `href`, so a
 * link like `#come-generare-varianti` lands on the heading itself regardless
 * of whether the permalink icon is visible. The scroll offset for the sticky
 * topbar is applied via plain CSS in guida.blade.php (`.wiki-page-content h2,
 * h3 { scroll-margin-top }`) rather than a `heading_class` here — the
 * Filament panel has no build step that would compile a Tailwind utility
 * class used inside Markdown-rendered content.
 */
final class MarkdownRenderer
{
    private static ?MarkdownConverter $converter = null;

    public static function toHtml(string $markdown): string
    {
        return (string) self::converter()->convert($markdown);
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter !== null) {
            return self::$converter;
        }

        $environment = new Environment([
            'html_input' => 'allow',
            'heading_permalink' => [
                'id_prefix' => '',
                'fragment_prefix' => '',
                'apply_id_to_heading' => true,
                'insert' => HeadingPermalinkProcessor::INSERT_AFTER,
                'html_class' => 'wiki-heading-permalink',
                'title' => __('pim.wiki.heading_permalink.title'),
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        return self::$converter = new MarkdownConverter($environment);
    }
}
