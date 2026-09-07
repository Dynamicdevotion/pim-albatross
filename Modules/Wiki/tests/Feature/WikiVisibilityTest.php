<?php

namespace Modules\Wiki\Tests\Feature;

use Modules\Wiki\Support\DocSection;
use Modules\Wiki\Support\DocTree;
use Tests\TestCase;

class WikiVisibilityTest extends TestCase
{
    public function test_woocommerce_section_stays_visible_but_marked_inactive_when_woosync_is_disabled(): void
    {
        config(['woosync.enabled' => false]);

        $section = $this->sectionsBySlug()['08-woocommerce'] ?? null;

        $this->assertNotNull($section, 'The section is shown, not hidden — it doubles as an upsell surface.');
        $this->assertTrue($section->inactive);
    }

    public function test_woocommerce_section_appears_active_when_woosync_is_enabled(): void
    {
        config(['woosync.enabled' => true]);

        $section = $this->sectionsBySlug()['08-woocommerce'] ?? null;

        $this->assertNotNull($section);
        $this->assertFalse($section->inactive);
    }

    public function test_core_sections_are_always_visible_and_active_regardless_of_the_flag(): void
    {
        config(['woosync.enabled' => false]);

        $sections = $this->sectionsBySlug();

        $this->assertArrayHasKey('01-primi-passi', $sections);
        $this->assertFalse($sections['01-primi-passi']->inactive);
        $this->assertArrayHasKey('02-prodotti', $sections);
        $this->assertFalse($sections['02-prodotti']->inactive);
    }

    public function test_sections_and_pages_are_ordered_by_front_matter_order(): void
    {
        $section = collect(DocTree::build())->firstWhere('slug', '02-prodotti');

        $this->assertNotNull($section);
        $this->assertSame('Prodotti', $section->title);
        $this->assertSame('Varianti', $section->pages[0]->title);
    }

    /**
     * @return array<string, DocSection>
     */
    private function sectionsBySlug(): array
    {
        return collect(DocTree::build())->keyBy('slug')->all();
    }
}
