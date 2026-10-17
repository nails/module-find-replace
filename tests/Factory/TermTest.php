<?php

namespace Tests\FindReplace\Factory;

use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Factory\Term;
use Nails\FindReplace\Factory\Term\Variant\Html;
use Nails\FindReplace\Factory\Term\Variant\Json;
use Nails\FindReplace\Factory\Term\Variant\Plaintext;
use PHPUnit\Framework\TestCase;

class TermTest extends TestCase
{
    private Term $term;

    public function setUp(): void
    {
        $this->term = new Term('http://www.test.com/test?test=test');
    }

    public function test_term_returns_variants(): void
    {
        $this->assertSame(
            [Plaintext::ENCODING, Json::ENCODING, Html::ENCODING],
            array_keys($this->term->getVariants())
        );
    }

    public function test_factory_returns_variants(): void
    {
        $term = Factory::factory('Term', Constants::MODULE_SLUG, 'http://www.test.com/test?test=test');

        $this->assertInstanceOf(Term::class, $term);
        $this->assertSame(
            [Plaintext::ENCODING, Json::ENCODING, Html::ENCODING],
            array_keys($term->getVariants())
        );
    }
}
