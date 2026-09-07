<?php

namespace Modules\Wiki\Tests\Unit;

use Modules\Wiki\Support\MarkdownRenderer;
use Modules\Wiki\Support\SearchHighlighter;
use Modules\Wiki\Support\TableOfContents;
use Tests\TestCase;

class MarkdownRenderingTest extends TestCase
{
    public function test_headings_get_a_stable_id_derived_from_their_text(): void
    {
        $html = MarkdownRenderer::toHtml("## Come generare varianti\n\ntesto");

        $this->assertStringContainsString('id="come-generare-varianti"', $html);
    }

    public function test_table_of_contents_extracts_h2_and_h3_in_document_order(): void
    {
        $html = MarkdownRenderer::toHtml(<<<'MD'
            ## Prima sezione

            testo

            ### Una sottosezione

            testo

            ## Seconda sezione

            testo
            MD);

        $toc = TableOfContents::extract($html);

        $this->assertSame(
            [
                ['level' => 2, 'id' => 'prima-sezione', 'text' => 'Prima sezione'],
                ['level' => 3, 'id' => 'una-sottosezione', 'text' => 'Una sottosezione'],
                ['level' => 2, 'id' => 'seconda-sezione', 'text' => 'Seconda sezione'],
            ],
            $toc,
        );
    }

    public function test_table_of_contents_is_empty_when_the_page_has_no_h2_or_h3(): void
    {
        $html = MarkdownRenderer::toHtml("# Solo un titolo\n\ntesto");

        $this->assertSame([], TableOfContents::extract($html));
    }

    public function test_highlight_wraps_the_matched_term_and_escapes_the_rest(): void
    {
        $result = SearchHighlighter::highlight('Le <Varianti> di prodotto', 'varianti');

        $this->assertSame('Le &lt;<mark>Varianti</mark>&gt; di prodotto', $result);
    }

    public function test_highlight_without_a_search_term_just_escapes(): void
    {
        $this->assertSame('Le &lt;Varianti&gt;', SearchHighlighter::highlight('Le <Varianti>', ''));
    }

    public function test_snippet_returns_an_excerpt_centered_on_the_match(): void
    {
        $body = str_repeat('parola ', 40).'termine cercato'.str_repeat(' altra', 40);

        $snippet = SearchHighlighter::snippet($body, 'termine cercato', radius: 10);

        $this->assertNotNull($snippet);
        $this->assertStringContainsString('termine cercato', $snippet);
        $this->assertStringStartsWith('…', $snippet);
        $this->assertStringEndsWith('…', $snippet);
    }

    public function test_snippet_is_null_when_the_term_does_not_occur(): void
    {
        $this->assertNull(SearchHighlighter::snippet('Corpo della pagina.', 'assente'));
    }
}
