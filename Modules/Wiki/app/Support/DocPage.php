<?php

namespace Modules\Wiki\Support;

/**
 * One docs/*.md file. `slug` is the identifier used in the page's URL and in
 * search results — `"02-prodotti/varianti"` for a page, or just the folder
 * name (e.g. `"02-prodotti"`) for a section's own `index.md`.
 */
final class DocPage
{
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly int $order,
        public readonly string $path,
    ) {}

    public function body(): string
    {
        return FrontMatter::parse(file_get_contents($this->path))['body'];
    }
}
