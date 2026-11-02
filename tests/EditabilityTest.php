<?php

namespace Tests\FindReplace;

use Nails\FindReplace\Editability;
use PHPUnit\Framework\TestCase;

class EditabilityTest extends TestCase
{
    public function test_each_option_maps_to_a_read_only_flag(): void
    {
        $this->assertNull(Editability::readOnly(Editability::ANY));
        $this->assertFalse(Editability::readOnly(Editability::REPLACEABLE));
        $this->assertTrue(Editability::readOnly(Editability::READ_ONLY));
    }

    public function test_an_unknown_option_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Editability::readOnly('editable');
    }
}
