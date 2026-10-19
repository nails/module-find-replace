<?php

namespace Nails\FindReplace\Console\Command;

use Nails\FindReplace\Factory\Location;
use Nails\FindReplace\Interfaces\Resolver;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Fields extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('find-replace:fields')
            ->setDescription('List discovered facets and other dynamic fields, such as tables');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $this->banner('Find & Replace: Fields');

        try {
            $registry = $this->schema()->registry();
            $facets = $registry->facets();
            $resolvers = $registry->resolvers();
            $locations = $this->schema()->locations();
        } catch (\InvalidArgumentException | \PDOException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::EXIT_CODE_FAILURE;
        }

        ksort($facets);
        $facetRows = [];

        foreach ($facets as $key => $label) {
            $facetRows[] = [$key, $label];
        }

        $this->section($output, 'Facets', ['Key', 'Label'], $facetRows);
        $this->section($output, 'Resolvers', ['Resolver', 'Tables'], $this->resolverRows($resolvers));
        $this->section($output, 'Tables', ['Table', 'Column', 'Access', 'Reason'], $this->tableRows($locations));

        return self::EXIT_CODE_SUCCESS;
    }

    /**
     * @param list<Resolver> $resolvers
     *
     * @return list<list<string>>
     */
    private function resolverRows(array $resolvers): array
    {
        $rows = [];

        foreach ($resolvers as $resolver) {
            $tables = $resolver->tables();
            sort($tables);
            $rows[] = [$resolver::class, implode(', ', $tables)];
        }

        usort($rows, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return $rows;
    }

    /**
     * @param list<Location> $locations
     *
     * @return list<list<string>>
     */
    private function tableRows(array $locations): array
    {
        $rows = [];

        foreach ($locations as $location) {
            $rows[] = [
                $location->getTable(),
                $location->getColumn(),
                $location->getAccess(),
                $location->getReadOnlyReason() ?? '',
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]],
        );

        return $rows;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    private function section(OutputInterface $output, string $title, array $headers, array $rows): void
    {
        $output->writeln($title . ': <info>' . count($rows) . '</info>');
        $this->renderTable($output, $headers, $rows);
        $output->writeln('');
    }
}
