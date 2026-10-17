<?php

namespace Tests\FindReplace\Output;

use Nails\FindReplace\Output\Json;
use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{
    public function test_read_rejects_an_unsupported_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fr');
        $this->assertIsString($path);

        try {
            file_put_contents($path, '{"version":2,"term":"a","case_sensitive":true,"context_length":80,"hits":[]}');

            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('version');
            (new Json())->read($path);
        } finally {
            unlink($path);
        }
    }
}
