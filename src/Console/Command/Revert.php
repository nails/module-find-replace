<?php

namespace Nails\FindReplace\Console\Command;

use Nails\FindReplace\Factory\Outcome;
use Nails\FindReplace\Factory\Run;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Revert extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('find-replace:revert')
            ->setDescription('Restore cells from a replace run when they are unchanged')
            ->addArgument('run-id', InputArgument::REQUIRED, 'The replace run to revert');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $this->banner('Find & Replace: Revert');

        try {
            $runId = $this->argument($input, 'run-id');
            $changes = $this->changes();

            if ($changes->forRun($this->database(), $runId) === []) {
                $output->writeln('<error>No changes found for run ' . $runId . '</error>');

                return self::EXIT_CODE_FAILURE;
            }

            $run = Run::cli();
            $outcomes = $this->engine()->revert($runId, $changes, $run);
        } catch (\InvalidArgumentException | \PDOException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return self::EXIT_CODE_FAILURE;
        }

        $output->writeln('Run: <comment>' . $runId . '</comment>');
        $restored = 0;
        $refused = 0;
        $failed = 0;

        foreach ($outcomes as $outcome) {
            if ($outcome->getStatus() === Outcome::APPLIED) {
                $restored++;
                continue;
            }

            $hit = $outcome->getHit();
            $label = 'refused';

            if ($outcome->getStatus() === Outcome::FAILED) {
                $failed++;
                $label = 'failed';
            } else {
                $refused++;
            }

            $output->writeln(sprintf(
                '  %s %s.%s #%s: %s',
                $label,
                $hit?->getTable() ?? '',
                $hit?->getMatched()?->getColumn() ?? $this->columnFromTitle($hit?->getTitle()),
                $hit?->getId() ?? '',
                $outcome->getReason() ?? '',
            ));
        }

        $output->writeln('Restored: <info>' . $restored . '</info>');
        $output->writeln('Refused: <info>' . $refused . '</info>');
        $output->writeln('Failed: <info>' . $failed . '</info>');

        if ($restored > 0) {
            $output->writeln('Run id: <comment>' . $run->getId() . '</comment>');
        }

        return $failed > 0 ? self::EXIT_CODE_FAILURE : self::EXIT_CODE_SUCCESS;
    }

    private function columnFromTitle(?string $title): string
    {
        if ($title === null || !str_contains($title, '.')) {
            return '';
        }

        $column = substr($title, (int) strpos($title, '.') + 1);
        $hash = strpos($column, ' #');

        return $hash === false ? $column : substr($column, 0, $hash);
    }
}
