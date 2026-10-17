<?php

namespace Nails\FindReplace\Output;

use Nails\FindReplace\Factory\Hit;

class Csv
{
    /**
     * @param list<Hit> $hits
     */
    public function write(string $path, array $hits, bool $caseSensitive): void
    {
        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            throw new \RuntimeException('Unable to build CSV output');
        }

        $header = [
            'table',
            'column',
            'primary_key',
            'title',
            'encoding',
            'term',
            'snippet',
            'read_only',
            'reason',
            'facets',
        ];
        fputcsv($handle, $header, ',', '"', '\\');

        foreach ($hits as $hit) {
            $matched = $hit->getMatched();
            $facets = [];

            foreach ($hit->getFacets() as $key => $value) {
                $facets[] = $key . '=' . $value;
            }

            fputcsv($handle, [
                $hit->getTable(),
                $matched?->getColumn() ?? '',
                $hit->getId(),
                $hit->getTitle() ?? '',
                $matched?->getEncoding() ?? '',
                $matched?->getTerm() ?? '',
                $matched?->getContentWithFixes('', '', $caseSensitive) ?? '',
                $hit->isReadOnly() ? 'yes' : 'no',
                $hit->getReadOnlyReason() ?? '',
                implode('; ', $facets),
            ], ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if (!is_string($csv)) {
            throw new \RuntimeException('Unable to build CSV output');
        }

        $this->put($path, $csv);
    }

    private function put(string $path, string $contents): void
    {
        $directory = dirname($path);

        if ($directory !== '' && !is_dir($directory)) {
            throw new \RuntimeException('Output directory does not exist: ' . $directory);
        }

        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('Unable to write ' . $path);
        }
    }
}
