<?php

namespace Nails\FindReplace\Console;

use Nails\FindReplace\Factory\Location;
use Symfony\Component\Console\Formatter\OutputFormatter;

class FieldList
{
    /**
     * @param array<string, array{label: string, values: list<string>|null}> $facets
     * @param list<array{name: string, tables: list<string>}> $resolvers
     * @param list<Location> $locations
     */
    public function render(
        array $facets,
        array $resolvers,
        array $locations,
        bool $columns,
        int $width,
    ): string {
        $byTable = $this->byTable($locations);
        $text = $this->facets($facets);
        $text .= "\n" . $this->resolvers($resolvers);
        $text .= "\n" . $this->tables($byTable, $columns, max($width, 20));

        return $text;
    }

    /**
     * @param array<string, array{label: string, values: list<string>|null}> $facets
     */
    private function facets(array $facets): string
    {
        $text = 'Facets: <info>' . count($facets) . "</info>\n";
        $text .= "  <comment>Pass as --facet=key:value</comment>\n";

        if ($facets === []) {
            return $text . "  None.\n";
        }

        foreach ($facets as $key => $facet) {
            $text .= "\n  <info>" . $this->plain($facet['label']) . '</info> (key: ' . $this->plain($key) . ")\n";
            $text .= $this->values($facet['values']);
        }

        return $text;
    }

    /**
     * @param list<string>|null $values
     */
    private function values(?array $values): string
    {
        if ($values === null) {
            return "    Accepts any value\n";
        }

        if ($values === []) {
            return '';
        }

        $text = '';

        foreach ($values as $value) {
            $text .= '    ' . $this->plain($value) . "\n";
        }

        return $text;
    }

    /**
     * @param list<array{name: string, tables: list<string>}> $resolvers
     */
    private function resolvers(array $resolvers): string
    {
        $text = 'Resolvers: <info>' . count($resolvers) . "</info>\n";

        if ($resolvers === []) {
            return $text . "  None discovered.\n";
        }

        $names = $this->names($resolvers);

        foreach ($resolvers as $resolver) {
            $text .= "\n  <info>" . $this->plain($names[$resolver['name']] ?? $resolver['name']) . "</info>\n";

            if ($resolver['tables'] === []) {
                $text .= "    None.\n";
                continue;
            }

            foreach ($resolver['tables'] as $table) {
                $text .= '    ' . $this->plain($table) . "\n";
            }
        }

        return $text;
    }

    /**
     * @param array<string, list<Location>> $byTable
     */
    private function tables(array $byTable, bool $columns, int $width): string
    {
        $names = array_keys($byTable);
        $text = 'Tables: <info>' . count($names) . "</info>\n";
        $text .= "  <comment>Pass as --table=name or --table=name.column</comment>\n";

        if ($names === []) {
            return $text . "  None.\n";
        }

        if (!$columns) {
            $text .= "  <comment>Re-run with -v for columns, access, and read-only reasons.</comment>\n\n";

            return $text . $this->grid($names, $width);
        }

        $text .= "\n";

        foreach ($byTable as $table => $locations) {
            $text .= '  ' . $this->plain($table) . "\n";
            $text .= $this->columnLines($locations) . "\n";
        }

        return $text;
    }

    /**
     * @param list<Location> $locations
     */
    private function columnLines(array $locations): string
    {
        $width = 0;

        foreach ($locations as $location) {
            $width = max($width, strlen($location->getColumn()));
        }

        $lines = [];

        foreach ($locations as $location) {
            $line = '    ' . $this->plain(str_pad($location->getColumn(), $width));
            $line .= '  ' . $this->plain(str_pad($location->getAccess(), strlen(Location::SEARCHABLE)));
            $reason = $location->getReadOnlyReason();

            if ($reason !== null && $reason !== '') {
                $line .= '  ' . $this->plain($reason);
            }

            $lines[] = rtrim($line);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $names
     */
    private function grid(array $names, int $width): string
    {
        $longest = 0;

        foreach ($names as $name) {
            $longest = max($longest, strlen($name));
        }

        $cell = $longest + 2;
        $perRow = max(1, intdiv(max($width - 2, $cell), $cell));
        $lines = [];

        foreach (array_chunk($names, $perRow) as $row) {
            $cells = [];
            $last = count($row) - 1;

            foreach ($row as $index => $name) {
                $cells[] = $index === $last ? $name : str_pad($name, $cell);
            }

            $lines[] = '  ' . $this->plain(implode('', $cells));
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<Location> $locations
     *
     * @return array<string, list<Location>>
     */
    private function byTable(array $locations): array
    {
        $byTable = [];

        foreach ($locations as $location) {
            $byTable[$location->getTable()][] = $location;
        }

        ksort($byTable);

        foreach ($byTable as $table => $columns) {
            usort(
                $columns,
                static fn (Location $left, Location $right): int => $left->getColumn() <=> $right->getColumn(),
            );
            $byTable[$table] = $columns;
        }

        return $byTable;
    }

    /**
     * @param list<array{name: string, tables: list<string>}> $resolvers
     *
     * @return array<string, string>
     */
    private function names(array $resolvers): array
    {
        $short = [];

        foreach ($resolvers as $resolver) {
            $short[$resolver['name']] = $this->short($resolver['name']);
        }

        $counts = array_count_values($short);
        $names = [];

        foreach ($short as $class => $label) {
            $names[$class] = ($counts[$label] ?? 0) > 1 ? $class : $label;
        }

        return $names;
    }

    private function short(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    private function plain(string $text): string
    {
        return OutputFormatter::escape($text);
    }
}
