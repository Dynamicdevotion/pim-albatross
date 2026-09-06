<?php

namespace Modules\Wiki\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Wiki\Support\DocPage;
use Modules\Wiki\Support\DocTree;
use Modules\Wiki\Support\MarkdownRenderer;

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
     * @return list<\Modules\Wiki\Support\DocSection>
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
     * @return list<\Modules\Wiki\Support\DocSection>
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
}
