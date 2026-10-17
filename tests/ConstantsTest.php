<?php

namespace Tests\FindReplace;

use Nails\FindReplace\Constants;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nails\FindReplace\Constants
 */
class ConstantsTest extends TestCase
{
    public function test_module_slug_is_correct(): void
    {
        self::assertSame('nails/module-find-replace', Constants::MODULE_SLUG);
    }
}
