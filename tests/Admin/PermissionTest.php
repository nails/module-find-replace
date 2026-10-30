<?php

namespace Tests\FindReplace\Admin;

require_once dirname(__DIR__, 2) . '/admin/controllers/FindReplace.php';

use Nails\Admin\FindReplace\FindReplace;
use Nails\FindReplace\Admin\Permission;
use Nails\FindReplace\Admin\Url;
use PHPUnit\Framework\TestCase;

class PermissionTest extends TestCase
{
    /**
     * The user group editor stores `admin:{module}:{controller}:{key}`, lower-cased,
     * for each key the controller offers.
     */
    public function test_constants_match_what_the_group_editor_stores(): void
    {
        $stored = array_map(
            static fn(string $key): string => strtolower('admin:findReplace:FindReplace:' . $key),
            array_keys(FindReplace::permissions()),
        );

        $this->assertSame([Permission::SEARCH, Permission::REPLACE], $stored);
    }

    public function test_module_name_routes_to_the_controller(): void
    {
        $composer = (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json');

        //  The admin router loads Nails\Admin\{ucfirst(moduleName)}\{Controller}, so the
        //  module name must be a valid namespace segment
        $this->assertMatchesRegularExpression('/"moduleName":\s*"findReplace"/', $composer);
        $this->assertSame('Nails\\Admin\\' . ucfirst('findReplace') . '\\FindReplace', FindReplace::class);
    }

    public function test_url_points_at_the_controller(): void
    {
        $this->assertStringEndsWith('/admin/findReplace/findReplace', Url::to());
        $this->assertStringEndsWith('/admin/findReplace/findReplace/results', Url::to('results'));
        $this->assertSame(Url::to('changes'), FindReplace::url('changes'));
    }
}
