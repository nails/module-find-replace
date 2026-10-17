<?php

namespace Nails\FindReplace\Console\Command;

use Nails\FindReplace\Output\Csv;
use Nails\FindReplace\Output\Json;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Search extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('find-replace:search')
            ->setDescription('Search text columns for one term')
            ->addArgument('term', InputArgument::REQUIRED, 'The term to find, used verbatim')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Write csv or json instead of a console table')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'File to write when --format is set');
        $this->addSharedOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $this->banner('Find & Replace: Search');

        try {
            $term = $this->argument($input, 'term');
            $request = $this->request($input);
            [$format, $path] = $this->outputTarget($input);
            $hits = $this->searchHits($term, $request);
        } catch (\InvalidArgumentException | \PDOException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::EXIT_CODE_FAILURE;
        }

        $output->writeln('Found <info>' . count($hits) . '</info> match(es).');

        if ($format === null || $path === null) {
            $this->renderHits($output, $hits, $request->isCaseSensitive());

            return self::EXIT_CODE_SUCCESS;
        }

        try {
            if ($format === 'csv') {
                (new Csv())->write($path, $hits, $request->isCaseSensitive());
            } else {
                (new Json())->write(
                    $path,
                    $term,
                    $request->isCaseSensitive(),
                    $request->getContextLength(),
                    $hits,
                );
            }
        } catch (\RuntimeException | \JsonException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::EXIT_CODE_FAILURE;
        }

        $output->writeln('Wrote <info>' . $path . '</info>');

        return self::EXIT_CODE_SUCCESS;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function outputTarget(InputInterface $input): array
    {
        $format = $input->getOption('format');
        $path = $input->getOption('output');
        $format = is_string($format) ? strtolower($format) : null;
        $path = is_string($path) && $path !== '' ? $path : null;

        if (($format === null) !== ($path === null)) {
            throw new \InvalidArgumentException('--format and --output are used together');
        }

        if ($format !== null && !in_array($format, ['csv', 'json'], true)) {
            throw new \InvalidArgumentException('--format must be csv or json');
        }

        return [$format, $path];
    }
}
