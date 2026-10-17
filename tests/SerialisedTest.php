<?php

namespace Tests\FindReplace;

use Nails\FindReplace\Serialised;
use PHPUnit\Framework\TestCase;

class SerialisedTest extends TestCase
{
    public function test_serialised_markers_that_unserialize_are_detected(): void
    {
        $this->assertTrue(Serialised::is('b:0;'));
        $this->assertTrue(Serialised::is('b:1;'));
        $this->assertTrue(Serialised::is('N;'));
        $this->assertTrue(Serialised::is('i:0;'));
        $this->assertTrue(Serialised::is('i:42;'));
        $this->assertTrue(Serialised::is('d:1.5;'));
        $this->assertTrue(Serialised::is('s:4:"test";'));
        $this->assertTrue(Serialised::is('a:0:{}'));
        $this->assertTrue(Serialised::is('O:8:"stdClass":0:{}'));
    }

    public function test_marker_prefixes_that_do_not_unserialize_are_plain_text(): void
    {
        $this->assertFalse(Serialised::is('a: not serialised'));
        $this->assertFalse(Serialised::is('s:3:"no"'));
        $this->assertFalse(Serialised::is('hello'));
        $this->assertFalse(Serialised::is(' b:0;'));
    }
}
