<?php

namespace Tests\FindReplace\Console;

use Nails\FindReplace\Console\FieldList;
use Nails\FindReplace\Factory\Location;
use Nails\FindReplace\Reason;
use PHPUnit\Framework\TestCase;

class FieldListTest extends TestCase
{
    public function test_typed_facets_list_accepted_values(): void
    {
        $text = (new FieldList())->render(
            [
                'resolver' => ['label' => 'Resolver', 'values' => null],
                'status' => ['label' => 'Status', 'values' => ['DRAFT', 'PUBLISHED']],
            ],
            [
                [
                    'name' => 'App\\FindReplace\\Resolver\\Article',
                    'tables' => ['app_article', 'app_article_localised'],
                ],
            ],
            [],
            false,
            80,
        );

        $this->assertStringContainsString("<info>Resolver</info> (key: resolver)\n    Accepts any value", $text);
        $this->assertStringContainsString("<info>Status</info> (key: status)\n    DRAFT\n    PUBLISHED", $text);
        $this->assertStringContainsString("<info>Article</info>\n    app_article\n    app_article_localised", $text);
        $this->assertStringNotContainsString('App\\FindReplace\\Resolver\\Article', $text);
    }

    public function test_columns_stay_behind_verbose_and_share_one_table_heading(): void
    {
        $locations = [
            new Location('fr_cli_fields', 'id', 'slug', Location::READ_ONLY, Reason::READ_ONLY),
            new Location('fr_cli_fields', 'id', 'body', Location::SEARCHABLE),
            new Location('fr_other', 'id', 'title', Location::SEARCHABLE),
        ];
        $list = new FieldList();

        $normal = $list->render([], [], $locations, false, 40);

        $this->assertStringContainsString('Re-run with -v', $normal);
        $this->assertStringContainsString('fr_cli_fields', $normal);
        $this->assertStringNotContainsString('searchable', $normal);
        $this->assertSame(1, substr_count($normal, 'fr_cli_fields'));

        $verbose = $list->render([], [], $locations, true, 40);

        $this->assertStringNotContainsString('Re-run with -v', $verbose);
        $this->assertMatchesRegularExpression(
            '/fr_cli_fields\n    body\s+searchable\n    slug\s+read-only\s+' . preg_quote(Reason::READ_ONLY, '/') . '/',
            $verbose,
        );
        $this->assertSame(1, substr_count($verbose, 'fr_cli_fields'));
        $this->assertStringNotContainsString('|', $verbose);
    }
}
