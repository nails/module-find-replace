<?php

namespace Nails\FindReplace\Console\Command;

use Nails\FindReplace\Console\FieldList;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Terminal;

class Fields extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('find-replace:fields')
            ->setDescription('List facets, resolvers, and tables for the CLI filters. -v adds columns');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $this->banner('Find & Replace: Fields');

        try {
            $text = (new FieldList())->render(
                $this->facetCatalogue(),
                $this->resolverCatalogue(),
                $this->schema()->locations(),
                $output->isVerbose(),
                $this->width(),
            );
        } catch (\InvalidArgumentException | \PDOException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::EXIT_CODE_FAILURE;
        }

        $this->writeReport($output, $text);

        return self::EXIT_CODE_SUCCESS;
    }

    /**
     * @return array<string, array{label: string, values: list<string>|null}>
     */
    private function facetCatalogue(): array
    {
        $registry = $this->schema()->registry();
        $values = $registry->facetValues();
        $facets = [];

        foreach ($registry->facets() as $key => $label) {
            $facets[$key] = [
                'label' => $label,
                'values' => $values[$key] ?? null,
            ];
        }

        ksort($facets);

        return $facets;
    }

    /**
     * @return list<array{name: string, tables: list<string>}>
     */
    private function resolverCatalogue(): array
    {
        $rows = [];

        foreach ($this->schema()->registry()->resolvers() as $resolver) {
            $tables = $resolver->tables();
            sort($tables);
            $rows[] = [
                'name' => $resolver::class,
                'tables' => $tables,
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => $left['name'] <=> $right['name'],
        );

        return $rows;
    }

    private function width(): int
    {
        $width = (new Terminal())->getWidth();

        return $width > 0 ? $width : 80;
    }

    private function writeReport(OutputInterface $output, string $tagged): void
    {
        $formatted = $output->getFormatter()->format($tagged) ?? '';

        if ($this->page($output, $formatted)) {
            return;
        }

        $output->write($formatted, false, OutputInterface::OUTPUT_RAW);
    }

    /**
     * Page on a terminal. Captured and piped output is written in full.
     */
    private function page(OutputInterface $output, string $formatted): bool
    {
        if (!$output instanceof StreamOutput || !defined('STDIN')) {
            return false;
        }

        $stream = $output->getStream();

        if (!is_resource($stream) || !is_resource(STDIN) || !stream_isatty($stream) || !stream_isatty(STDIN)) {
            return false;
        }

        $height = (new Terminal())->getHeight();
        $room = $height - 7;

        if ($room < 5) {
            return false;
        }

        $lines = explode("\n", rtrim($formatted, "\n"));

        if (count($lines) <= $room) {
            return false;
        }

        $offset = 0;
        $total = count($lines);

        while ($offset < $total) {
            $slice = array_slice($lines, $offset, $room);
            $output->write(implode("\n", $slice) . "\n", false, OutputInterface::OUTPUT_RAW);
            $offset += count($slice);

            if ($offset >= $total) {
                break;
            }

            $output->write('<comment>-- More -- Enter for the next page, q to stop</comment> ');
            $answer = fgets(STDIN);
            $output->write("\033[1A\033[2K", false, OutputInterface::OUTPUT_RAW);

            if (!is_string($answer) || str_starts_with($answer, 'q')) {
                break;
            }
        }

        return true;
    }
}
