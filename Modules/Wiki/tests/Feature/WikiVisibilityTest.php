<?php

namespace Modules\Wiki\Tests\Feature;

use Modules\Wiki\Support\DocTree;
use Tests\TestCase;

class WikiVisibilityTest extends TestCase
{
    public function test_woocommerce_section_is_hidden_when_woosync_is_disabled(): void
    {
        config(['woosync.enabled' => false]);

        $this->assertNotContains('08-woocommerce', $this->sectionSlugs());
    }

    public function test_woocommerce_section_appears_when_woosync_is_enabled(): void
    {
        config(['woosync.enabled' => true]);

        $this->assertContains('08-woocommerce', $this->sectionSlugs());
    }

    public function test_core_sections_are_always_visible_regardless_of_the_flag(): void
    {
        config(['woosync.enabled' => false]);

        $slugs = $this->sectionSlugs();

        $this->assertContains('01-primi-passi', $slugs);
        $this->assertContains('02-prodotti', $slugs);
    }

    public function test_sections_and_pages_are_ordered_by_front_matter_order(): void
    {
        $section = collect(DocTree::build())->firstWhere('slug', '02-prodotti');

        $this->assertNotNull($section);
        $this->assertSame('Prodotti', $section->title);
        $this->assertSame('Varianti', $section->pages[0]->title);
    }

    /**
     * @return list<string>
     */
    private function sectionSlugs(): array
    {
        return array_map(fn ($section) => $section->slug, DocTree::build());
    }
}
