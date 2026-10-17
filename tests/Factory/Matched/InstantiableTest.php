<?php

namespace Tests\FindReplace\Factory\Matched;

use Nails\FindReplace\Factory\Matched;
use PHPUnit\Framework\TestCase;

class InstantiableTest extends TestCase
{
    private Matched $match;

    public function setUp(): void
    {
        $this->match = new Matched(
            'test_table',
            'test_column',
            'plaintext',
            'test',
            '1',
            'Test: this is a test!'
        );
    }

    public function test_case_sensitive_content_fixes(): void
    {
        $this->assertEquals(
            'Test: this is a ¡test!!',
            $this->match->getContentWithFixes('¡', '!', true)
        );
    }

    public function test_case_insensitive_content_fixes(): void
    {
        $this->assertEquals(
            '¡Test!: this is a ¡test!!',
            $this->match->getContentWithFixes('¡', '!', false)
        );
    }

    public function test_string_callback_is_applied(): void
    {
        $callback = function (string $string): string {
            return str_replace(':', ' —', $string);
        };

        $this->assertEquals(
            'Test — this is a test!',
            $this->match->getContentWithFixes('', '', false, $callback)
        );
    }

    public function test_string_callback_is_not_applied_to_fixes(): void
    {
        $callback = function (string $string): string {
            return str_replace('!', '?', $string);
        };

        $this->assertEquals(
            '¡Test!: this is a ¡test!?',
            $this->match->getContentWithFixes('¡', '!', false, $callback)
        );
    }

    public function test_long_content_is_windowed_around_the_term(): void
    {
        $match = new Matched(
            'test_table',
            'test_column',
            'plaintext',
            'test',
            '1',
            str_repeat('a', 200) . 'test' . str_repeat('b', 200)
        );

        $this->assertSame(
            str_repeat('a', 80) . 'test' . str_repeat('b', 80),
            $match->getContentWithFixes('', '')
        );
    }
}
