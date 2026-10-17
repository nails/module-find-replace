<?php

namespace Tests\FindReplace\Factory\Term;

use Nails\FindReplace\Factory\Term\Variant\Html;
use Nails\FindReplace\Factory\Term\Variant\Json;
use Nails\FindReplace\Factory\Term\Variant\Plaintext;
use PHPUnit\Framework\TestCase;

class VariantTest extends TestCase
{
    private string $term;

    public function setUp(): void
    {
        $this->term = 'http://www.test.com/test?test[]=1&test[]=2';
    }

    public function test_plaintext_variant(): void
    {
        $variant = new Plaintext($this->term);

        $this->assertEquals($this->term, $variant->getTerm());
    }

    public function test_json_variant(): void
    {
        $variant = new Json($this->term);

        $this->assertEquals(
            'http:\/\/www.test.com\/test?test[]=1&test[]=2',
            $variant->getTerm()
        );
    }

    public function test_html_variant(): void
    {
        $variant = new Html($this->term);

        $this->assertEquals(htmlspecialchars($this->term), $variant->getTerm());
    }
}
