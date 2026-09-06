<?php

namespace Modules\Wiki\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Wraps CommonMark with the GFM extensions the docs actually use (tables,
 * bare-URL autolinking) — both ship inside the `league/commonmark` package
 * already pulled in transitively by Laravel, no new dependency needed.
 * `html_input` is left at "allow" because docs/*.md is developer-authored and
 * versioned in git, not user-submitted content.
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

        $environment = new Environment(['html_input' => 'allow']);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new AutolinkExtension());

        return self::$converter = new MarkdownConverter($environment);
    }
}
