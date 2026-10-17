<?php

namespace Tests\FindReplace\Factory\Matched;

use Nails\FindReplace\Factory\Matched;
use Nails\FindReplace\Factory\Matched\Replacement;
use Nails\FindReplace\Factory\Term;
use PHPUnit\Framework\TestCase;

class ReplacementTest extends TestCase
{
    private function replacement(string $term, string $replacement, string $content): Replacement
    {
        return new Replacement(
            new Matched(
                'test_table',
                'test_column',
                'plaintext',
                $term,
                '1',
                $content
            ),
            new Term($replacement)
        );
    }

    public function test_only_replaced_terms_are_decorated(): void
    {
        $replacement = $this->replacement(
            '/insights',
            '/insight',
            'the old url was /insights and we are replacing it with /insight'
        );

        $this->assertEquals(
            'the old url was [/insight] and we are replacing it with /insight',
            $replacement->getReplacementContentWithFixes('[', ']')
        );
    }

    public function test_every_replaced_term_is_decorated(): void
    {
        $replacement = $this->replacement(
            'test',
            'exam',
            'test: this is a test!'
        );

        $this->assertEquals(
            '[exam]: this is a [exam]!',
            $replacement->getReplacementContentWithFixes('[', ']')
        );
    }

    public function test_unmatched_content_is_returned_verbatim(): void
    {
        $replacement = $this->replacement(
            'missing',
            'exam',
            'test: this is a test!'
        );

        $this->assertEquals(
            'test: this is a test!',
            $replacement->getReplacementContentWithFixes('[', ']')
        );
    }

    public function test_case_insensitive_matches_are_replaced_and_decorated(): void
    {
        $replacement = $this->replacement(
            'test',
            'exam',
            'Test: this is a test!'
        );

        $this->assertEquals(
            '[exam]: this is a [exam]!',
            $replacement->getReplacementContentWithFixes('[', ']', false)
        );
    }

    public function test_case_sensitive_preview_leaves_other_cases(): void
    {
        $replacement = $this->replacement(
            'test',
            'exam',
            'Test: this is a test!'
        );

        $this->assertEquals(
            'Test: this is a [exam]!',
            $replacement->getReplacementContentWithFixes('[', ']', true)
        );
    }

    public function test_decorated_content_matches_replacement_content(): void
    {
        $replacement = $this->replacement(
            '/insights',
            '/insight',
            '/insights, /insight and /insights again'
        );

        $this->assertEquals(
            $replacement->getReplacementContent(),
            str_replace(
                ['[', ']'],
                '',
                $replacement->getReplacementContentWithFixes('[', ']')
            )
        );
    }

    public function test_string_callback_is_applied_to_content_and_term(): void
    {
        $replacement = $this->replacement(
            'test',
            '<b>',
            'a test & another test'
        );

        $this->assertEquals(
            'a [&lt;b&gt;] &amp; another [&lt;b&gt;]',
            $replacement->getReplacementContentWithFixes('[', ']', true, 'htmlspecialchars')
        );
    }

    public function test_empty_replacement_term_returns_empty_string(): void
    {
        $replacement = $this->replacement('test', '', 'a test');

        $this->assertEquals('', $replacement->getReplacementContentWithFixes('[', ']'));
    }
}
