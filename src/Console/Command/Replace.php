<?php

namespace Nails\FindReplace\Console\Command;

use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Factory\Matched\Replacement;
use Nails\FindReplace\Factory\Outcome;
use Nails\FindReplace\Factory\Run;
use Nails\FindReplace\Factory\Term;
use Nails\FindReplace\Output\Json;
use Nails\FindReplace\Reason;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Replace extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('find-replace:replace')
            ->setDescription('Replace one term, or the rows in a search JSON file')
            ->addArgument('term', InputArgument::REQUIRED, 'The term to find, used verbatim')
            ->addArgument('replacement', InputArgument::REQUIRED, 'The replacement text')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Apply rows from a search --format=json file')
            ->addOption('yes', null, InputOption::VALUE_NONE, 'Apply without asking')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview only');
        $this->addSharedOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $this->banner('Find & Replace: Replace');

        try {
            $term = $this->argument($input, 'term');
            $replacement = $this->argument($input, 'replacement', true);
            $request = $this->request($input);
            $from = $input->getOption('from');
            $from = is_string($from) && $from !== '' ? $from : null;
            $caseSensitive = $request->isCaseSensitive();

            if ($from !== null) {
                $document = (new Json())->read($from);
                $caseSensitive = $document->isCaseSensitive();

                if ($input->getOption('case-insensitive') && $caseSensitive) {
                    $output->writeln(
                        '<comment>--case-insensitive is ignored. The file records a case-sensitive search.</comment>',
                    );
                }

                $output->writeln('From: <info>' . $from . '</info>');
                $hits = $this->narrow($document->toHits($this->schema()), $request, true);
            } else {
                $hits = $this->searchHits($term, $request);
            }
        } catch (\InvalidArgumentException | \PDOException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::EXIT_CODE_FAILURE;
        }

        $output->writeln('Term: <info>' . $term . '</info>');
        $output->writeln('Replacement: <info>' . $replacement . '</info>');
        $output->writeln(
            $caseSensitive
                ? 'Case-sensitive'
                : 'Case-insensitive',
        );
        $output->writeln('');

        [$writable, $skipped, $failed, $previewRows, $skipRows] = $this->classify(
            $hits,
            $replacement,
            $caseSensitive,
        );

        if ($previewRows !== []) {
            $output->writeln('Would replace:');
            $this->renderTable($output, ['Table', 'Column', 'ID', 'Replacement', 'Preview'], $previewRows);
        }

        if ($skipRows !== []) {
            $output->writeln('Would skip:');
            $this->renderTable($output, ['Table', 'Column', 'ID', 'Reason'], $skipRows);
        }

        if ($input->getOption('dry-run')) {
            $output->writeln('Would apply: <info>' . count($writable) . '</info>');
            $output->writeln('Would skip: <info>' . $skipped . '</info>');
            $output->writeln('Failed: <info>' . $failed . '</info>');
            $output->writeln('<comment>Dry run: no changes written</comment>');

            return $failed > 0 ? self::EXIT_CODE_FAILURE : self::EXIT_CODE_SUCCESS;
        }

        if ($writable === []) {
            $output->writeln('Nothing to replace.');
            $this->summary($output, 0, $skipped, $failed, null);

            return $failed > 0 ? self::EXIT_CODE_FAILURE : self::EXIT_CODE_SUCCESS;
        }

        if (!$input->getOption('yes') && !$this->confirmed($input, $output)) {
            $output->writeln('No changes written.');

            return self::EXIT_CODE_SUCCESS;
        }

        $run = Run::cli();

        try {
            $outcomes = $this->engine()->replace(
                $writable,
                $replacement,
                $caseSensitive,
                $this->changes(),
                $run,
            );
        } catch (\PDOException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::EXIT_CODE_FAILURE;
        }

        $applied = 0;

        foreach ($outcomes as $outcome) {
            if ($outcome->getStatus() === Outcome::APPLIED) {
                $applied++;
                continue;
            }

            if ($outcome->getStatus() === Outcome::FAILED) {
                $failed++;
            } else {
                $skipped++;
            }

            $hit = $outcome->getHit();
            $matched = $hit?->getMatched();
            $output->writeln(sprintf(
                '  %s %s.%s #%s: %s',
                $outcome->getStatus(),
                $hit?->getTable() ?? '',
                $matched?->getColumn() ?? '',
                $hit?->getId() ?? '',
                $outcome->getReason() ?? '',
            ));
        }

        $this->summary($output, $applied, $skipped, $failed, $applied > 0 ? $run->getId() : null);

        return $failed > 0 ? self::EXIT_CODE_FAILURE : self::EXIT_CODE_SUCCESS;
    }

    /**
     * @param list<Hit> $hits
     *
     * @return array{0: list<Hit>, 1: int, 2: int, 3: list<list<string>>, 4: list<list<string>>}
     */
    private function classify(array $hits, string $replacement, bool $caseSensitive): array
    {
        $writable = [];
        $skipped = 0;
        $failed = 0;
        $previewRows = [];
        $skipRows = [];

        foreach ($hits as $hit) {
            $matched = $hit->getMatched();
            $column = $matched?->getColumn() ?? '';
            $reason = $this->skipReason($hit);

            if ($reason !== null || $matched === null) {
                $skipped++;
                $skipRows[] = [$hit->getTable(), $column, $hit->getId(), $reason ?? 'match is missing'];
                continue;
            }

            try {
                $preview = new Replacement($matched, new Term($replacement));
            } catch (\DomainException $exception) {
                $failed++;
                $skipRows[] = [$hit->getTable(), $column, $hit->getId(), $exception->getMessage()];
                continue;
            }

            $writable[] = $hit;
            $previewRows[] = [
                $hit->getTable(),
                $column,
                $hit->getId(),
                $preview->getReplacementTerm(),
                $this->flat($preview->getReplacementContentWithFixes('«', '»', $caseSensitive)),
            ];
        }

        return [$writable, $skipped, $failed, $previewRows, $skipRows];
    }

    private function skipReason(Hit $hit): ?string
    {
        if ($hit->getMatched() === null) {
            return 'match is missing';
        }

        if ($hit->isReadOnly()) {
            return $hit->getReadOnlyReason() ?? Reason::READ_ONLY;
        }

        return null;
    }
}
