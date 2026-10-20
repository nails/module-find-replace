<?php

namespace Nails\FindReplace\Console\Command;

use Nails\FindReplace\Console\FieldList;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
                $this->terminalWidth(),
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

    private function writeReport(OutputInterface $output, string $tagged): void
    {
        $this->emit($output, $output->getFormatter()->format($tagged) ?? '');
    }
}
