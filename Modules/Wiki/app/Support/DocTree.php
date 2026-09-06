<?php

namespace Modules\Wiki\Support;

/**
 * Scans docs/ (folder = section, .md file = page) and turns it into an
 * ordered, already-visibility-filtered tree. Nothing here touches the
 * database: the wiki has no content of its own beyond what's on disk.
 */
final class DocTree
{
    /**
     * @return list<DocSection>
     */
    public static function build(): array
    {
        $docsPath = config('wiki.docs_path');

        if (! is_dir($docsPath)) {
            return [];
        }

        $sections = [];

        foreach (glob($docsPath.'/*', GLOB_ONLYDIR) as $folder) {
            $slug = basename($folder);

            if (! self::isVisible($slug)) {
                continue;
            }

            $section = self::buildSection($slug, $folder);

            if ($section !== null) {
                $sections[] = $section;
            }
        }

        usort($sections, fn (DocSection $a, DocSection $b): int => [$a->order, $a->title] <=> [$b->order, $b->title]);

        return $sections;
    }

    /**
     * @param  list<DocSection>  $sections
     * @return list<DocPage>
     */
    public static function flatten(array $sections): array
    {
        $pages = [];

        foreach ($sections as $section) {
            $pages[] = $section->indexPage;

            foreach ($section->pages as $page) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    private static function buildSection(string $slug, string $folder): ?DocSection
    {
        $indexPath = $folder.'/index.md';

        if (! is_file($indexPath)) {
            return null;
        }

        [$title, $order] = self::titleAndOrder($indexPath, $slug);

        $indexPage = new DocPage(slug: $slug, title: $title, order: $order, path: $indexPath);

        $pages = [];

        foreach (glob($folder.'/*.md') as $file) {
            if (basename($file) === 'index.md') {
                continue;
            }

            $fileName = pathinfo($file, PATHINFO_FILENAME);
            [$pageTitle, $pageOrder] = self::titleAndOrder($file, $fileName);

            $pages[] = new DocPage(slug: $slug.'/'.$fileName, title: $pageTitle, order: $pageOrder, path: $file);
        }

        usort($pages, fn (DocPage $a, DocPage $b): int => [$a->order, $a->title] <=> [$b->order, $b->title]);

        return new DocSection(slug: $slug, title: $title, order: $order, indexPage: $indexPage, pages: $pages);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function titleAndOrder(string $path, string $fallbackName): array
    {
        $attributes = FrontMatter::parse(file_get_contents($path))['attributes'];

        $title = is_string($attributes['title'] ?? null)
            ? $attributes['title']
            : self::prettify($fallbackName);

        $order = is_int($attributes['order'] ?? null)
            ? $attributes['order']
            : self::numericPrefix($fallbackName);

        return [$title, $order];
    }

    private static function prettify(string $name): string
    {
        return ucfirst(str_replace('-', ' ', preg_replace('/^\d+-/', '', $name) ?? $name));
    }

    private static function numericPrefix(string $name): int
    {
        return preg_match('/^(\d+)-/', $name, $matches) === 1 ? (int) $matches[1] : PHP_INT_MAX;
    }

    private static function isVisible(string $slug): bool
    {
        $check = config("wiki.section_visibility.{$slug}");

        return $check === null || $check();
    }
}
