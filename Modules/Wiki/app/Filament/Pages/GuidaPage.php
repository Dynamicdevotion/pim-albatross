<?php

namespace Modules\Wiki\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Wiki\Support\DocPage;
use Modules\Wiki\Support\DocSection;
use Modules\Wiki\Support\DocTree;
use Modules\Wiki\Support\MarkdownRenderer;
use Modules\Wiki\Support\SearchHighlighter;
use Modules\Wiki\Support\TableOfContents;

/**
 * Renders docs/*.md as an in-panel wiki: a two-level tree (folder = section,
 * file = page) rebuilt from disk on every request — there is no database
 * content to invalidate. Sections tied to an optional module are already
 * filtered out of the tree by {@see DocTree::build()} before this page ever
 * sees them (see config('wiki.section_visibility')), and the active page is
 * re-resolved against that same filtered tree so a bookmarked/shared link to
 * a since-hidden section can't render anything either.
 */
class GuidaPage extends Page
{
    protected string $view = 'wiki::filament.pages.guida';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $slug = 'guida';

    protected static ?int $navigationSort = 99;

    #[Url(as: 'p')]
    public ?string $activePage = null;

    #[Url(as: 'q')]
    public string $search = '';

    public static function getNavigationLabel(): string
    {
        return __('pim.wiki.nav.label');
    }

    public function getTitle(): string
    {
        return __('pim.wiki.page.title');
    }

    public static function canAccess(): bool
    {
        return true;
    }

    /**
     * "Guida" > section title > page title, skipping the page title when
     * it's the section's own index page. Reuses Filament's native
     * breadcrumb bar (rendered by the panel layout from this method) rather
     * than a bespoke breadcrumb UI.
     *
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        $page = $this->activeDocPage;

        if ($page === null) {
            return [$this->getTitle()];
        }

        $section = $this->sectionContaining($page);
        $indexUrl = static::getUrl();

        if ($section === null) {
            return [$indexUrl => $this->getTitle(), $page->title];
        }

        if ($section->indexPage->slug === $page->slug) {
            return [$indexUrl => $this->getTitle(), $section->title];
        }

        return [
            $indexUrl => $this->getTitle(),
            static::getUrl(['p' => $section->indexPage->slug]) => $section->title,
            $page->title,
        ];
    }

    /**
     * @return list<DocSection>
     */
    #[Computed]
    public function tree(): array
    {
        return DocTree::build();
    }

    /**
     * The tree shown in the sidebar: narrowed by {@see $search} when set, but
     * this never affects which page {@see $content} resolves to — searching
     * just helps you find a page, it doesn't navigate away from the one
     * you're reading.
     *
     * @return list<DocSection>
     */
    #[Computed]
    public function filteredTree(): array
    {
        $needle = Str::lower(trim($this->search));

        if ($needle === '') {
            return $this->tree;
        }

        $matches = fn (DocPage $page): bool => Str::contains(Str::lower($page->title), $needle)
            || Str::contains(Str::lower($page->body()), $needle);

        $filtered = [];

        foreach ($this->tree as $section) {
            if (array_filter([$section->indexPage, ...$section->pages], $matches) !== []) {
                $filtered[] = $section;
            }
        }

        return $filtered;
    }

    #[Computed]
    public function activeDocPage(): ?DocPage
    {
        $pages = DocTree::flatten($this->tree);

        foreach ($pages as $page) {
            if ($page->slug === $this->activePage) {
                return $page;
            }
        }

        return $pages[0] ?? null;
    }

    #[Computed]
    public function pageHtml(): string
    {
        return $this->activeDocPage === null ? '' : MarkdownRenderer::toHtml($this->activeDocPage->body());
    }

    /**
     * The current page's h2/h3 outline, for the right-hand "on this page"
     * summary.
     *
     * @return list<array{level: int, id: string, text: string}>
     */
    #[Computed]
    public function pageToc(): array
    {
        return TableOfContents::extract($this->pageHtml);
    }

    /**
     * $text with the current search term wrapped in `<mark>` (already
     * HTML-escaped) — used for both sidebar titles and snippets so a match
     * is visible, not just the fact that a page made it into the filtered
     * list.
     */
    public function highlight(string $text): string
    {
        return SearchHighlighter::highlight($text, $this->search);
    }

    /**
     * A short excerpt of $page's body around the search match, or null when
     * there's no active search or the match was in the title only.
     */
    public function searchSnippet(DocPage $page): ?string
    {
        if (trim($this->search) === '') {
            return null;
        }

        return SearchHighlighter::snippet($page->body(), $this->search);
    }

    private function sectionContaining(DocPage $page): ?DocSection
    {
        foreach ($this->tree as $section) {
            if ($section->indexPage->slug === $page->slug) {
                return $section;
            }

            foreach ($section->pages as $candidate) {
                if ($candidate->slug === $page->slug) {
                    return $section;
                }
            }
        }

        return null;
    }
}
