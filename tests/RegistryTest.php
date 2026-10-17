<?php

namespace Tests\FindReplace;

require_once __DIR__ . '/Support/ListExclusion.php';

use Nails\FindReplace\Factory\Location;
use Nails\FindReplace\Service\Registry;
use PHPUnit\Framework\TestCase;
use Tests\FindReplace\Support\ListExclusion;

class RegistryTest extends TestCase
{
    public function test_table_entry_hides_the_whole_table(): void
    {
        $registry = $this->registry(hidden: ['zz_table']);

        $this->assertSame(Location::HIDDEN, $registry->access('zz_table', 'body'));
        $this->assertSame(Location::HIDDEN, $registry->access('zz_table', 'title'));
        $this->assertSame(Location::SEARCHABLE, $registry->access('zz_table_extra', 'body'));
    }

    public function test_table_star_entry_hides_the_whole_table(): void
    {
        $registry = $this->registry(hidden: ['zz_table.*']);

        $this->assertSame(Location::HIDDEN, $registry->access('zz_table', 'body'));
        $this->assertSame(Location::SEARCHABLE, $registry->access('other_table', 'body'));
    }

    public function test_column_entry_hides_one_column(): void
    {
        $registry = $this->registry(hidden: ['zz_table.body']);

        $this->assertSame(Location::HIDDEN, $registry->access('zz_table', 'body'));
        $this->assertSame(Location::SEARCHABLE, $registry->access('zz_table', 'title'));
    }

    public function test_regex_entry_matches_table_column(): void
    {
        $registry = $this->registry(hidden: ['/^zz_family_/']);

        $this->assertSame(Location::HIDDEN, $registry->access('zz_family_log', 'body'));
        $this->assertSame(Location::SEARCHABLE, $registry->access('zz_other', 'body'));
    }

    public function test_regex_flags_are_honoured(): void
    {
        $registry = $this->registry(readOnly: ['/^ZZ_TABLE\./i']);

        $this->assertSame(Location::READ_ONLY, $registry->access('zz_table', 'body'));
        $this->assertSame(Location::SEARCHABLE, $registry->access('zz_other', 'body'));
    }

    public function test_read_only_entry_forms(): void
    {
        $this->assertSame(
            Location::READ_ONLY,
            $this->registry(readOnly: ['zz_table'])->access('zz_table', 'body')
        );
        $this->assertSame(
            Location::READ_ONLY,
            $this->registry(readOnly: ['zz_table.*'])->access('zz_table', 'title')
        );
        $this->assertSame(
            Location::READ_ONLY,
            $this->registry(readOnly: ['zz_table.body'])->access('zz_table', 'body')
        );
        $this->assertSame(
            Location::SEARCHABLE,
            $this->registry(readOnly: ['zz_table.body'])->access('zz_table', 'title')
        );
    }

    public function test_invalid_regex_throws_when_the_registry_loads(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('/[/');

        $this->registry(hidden: ['/[/']);
    }

    public function test_invalid_read_only_regex_throws_when_the_registry_loads(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('/(/');

        $this->registry(readOnly: ['/(/']);
    }

    public function test_hidden_beats_read_only(): void
    {
        $registry = $this->registry(
            hidden: ['zz_table.notes'],
            readOnly: ['zz_table.notes'],
        );

        $this->assertSame(Location::HIDDEN, $registry->access('zz_table', 'notes'));
    }

    public function test_package_hides_its_tables_and_literal_token_columns(): void
    {
        $registry = new Registry([]);

        $this->assertSame(Location::HIDDEN, $registry->access('nails_find_replace_search', 'term'));
        $this->assertSame(Location::HIDDEN, $registry->access('nails_find_replace_result', 'snippet'));
        $this->assertSame(Location::HIDDEN, $registry->access('nails_find_replace_result_facet', 'value'));
        $this->assertSame(Location::HIDDEN, $registry->access('nails_find_replace_change', 'value_before'));
        $this->assertSame(Location::SEARCHABLE, $registry->access('nails_find_replace_search_extra', 'term'));
        $this->assertSame(Location::HIDDEN, $registry->access('any_table', 'token'));
        $this->assertSame(Location::HIDDEN, $registry->access('any_table', 'ref'));
        $this->assertSame(Location::SEARCHABLE, $registry->access('any_table', 'claim_token'));
        $this->assertSame(Location::READ_ONLY, $registry->access('any_table', 'slug'));
        $this->assertSame(Location::SEARCHABLE, $registry->access('any_table', 'draft_slug'));
        $this->assertSame(Location::SEARCHABLE, $registry->access('any_table', 'slug_from'));
    }

    public function test_discovered_registry_loads(): void
    {
        $registry = new Registry();

        $this->assertSame(Location::HIDDEN, $registry->access('pages', 'token'));
        $this->assertSame('Resolver', $registry->facets()['resolver'] ?? null);
    }

    /**
     * @param list<string> $hidden
     * @param list<string> $readOnly
     */
    private function registry(array $hidden = [], array $readOnly = []): Registry
    {
        return new Registry([new ListExclusion($hidden, $readOnly)]);
    }
}
