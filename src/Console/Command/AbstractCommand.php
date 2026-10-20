<?php

namespace Nails\FindReplace\Console\Command;

use Nails\Common\Service\PDODatabase;
use Nails\Console\Command\Base;
use Nails\Factory;
use Nails\FindReplace\Console\Request;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Exclusion\Pattern;
use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Factory\Query;
use Nails\FindReplace\Model\Change;
use Nails\FindReplace\Scope;
use Nails\FindReplace\Service\Engine;
use Nails\FindReplace\Service\Schema;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Terminal;

abstract class AbstractCommand extends Base
{
    protected function addSharedOptions(): void
    {
        $this
            ->addOption(
                'table',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Limit to a table, table.column, or /regex/ (repeatable)',
                [],
            )
            ->addOption(
                'exclude-table',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Skip a table, table.column, or /regex/ (repeatable)',
                [],
            )
            ->addOption(
                'case-insensitive',
                null,
                InputOption::VALUE_NONE,
                'Match without regard to case (default is case-sensitive)',
            )
            ->addOption(
                'resolved-only',
                null,
                InputOption::VALUE_NONE,
                'Keep hits a resolver identified, and drop unresolved table.column hits',
            )
            ->addOption(
                'facet',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Keep hits whose facet equals key:value (repeatable, AND)',
                [],
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum number of hits after facet and resolved-only filters',
            )
            ->addOption(
                'context-length',
                null,
                InputOption::VALUE_REQUIRED,
                'Characters of context to keep around each match',
                '80',
            );
    }

    protected function request(InputInterface $input): Request
    {
        return new Request(
            !$this->flag($input, 'case-insensitive'),
            $this->entries($input, 'table'),
            $this->entries($input, 'exclude-table'),
            $this->limit($input),
            $this->contextLength($input),
            $this->flag($input, 'resolved-only'),
            $this->facets($input),
        );
    }

    protected function argument(InputInterface $input, string $name, bool $allowEmpty = false): string
    {
        $value = $input->getArgument($name);

        if (!is_string($value) || (!$allowEmpty && $value === '')) {
            throw new \InvalidArgumentException($name . ' is required');
        }

        return $value;
    }

    /**
     * Facet and resolved-only filters run after search. Limit runs after those filters.
     *
     * @return list<Hit>
     */
    protected function searchHits(string $term, Request $request): array
    {
        $post = $request->getFacets() !== [] || $request->isResolvedOnly();
        $query = new Query(
            $term,
            $request->isCaseSensitive(),
            $request->getTables(),
            $request->getExcludeTables(),
            $post ? null : $request->getLimit(),
            $request->getContextLength(),
        );

        return $this->narrow($this->engine()->search($query), $request, $post);
    }

    /**
     * @param list<Hit> $hits
     *
     * @return list<Hit>
     */
    protected function narrow(array $hits, Request $request, bool $applyLimit): array
    {
        $filtered = [];

        foreach ($hits as $hit) {
            if ($request->isResolvedOnly() && $hit->getEntityLabel() === null) {
                continue;
            }

            $facets = $hit->getFacets();
            $matches = true;

            foreach ($request->getFacets() as [$key, $value]) {
                if (($facets[$key] ?? null) !== $value) {
                    $matches = false;
                    break;
                }
            }

            if (!$matches) {
                continue;
            }

            $matched = $hit->getMatched();

            if (!Scope::includes(
                $hit->getTable(),
                $matched?->getColumn() ?? '',
                $request->getTables(),
                $request->getExcludeTables(),
            )) {
                continue;
            }

            $filtered[] = $hit;
        }

        if ($applyLimit && $request->getLimit() !== null) {
            return array_slice($filtered, 0, $request->getLimit());
        }

        return $filtered;
    }

    /**
     * @param list<Hit> $hits
     */
    protected function renderHits(OutputInterface $output, array $hits, bool $caseSensitive): void
    {
        $headers = ['Table', 'Column', 'ID', 'Title', 'Snippet', 'Reason'];
        $rows = [];

        foreach ($hits as $hit) {
            $matched = $hit->getMatched();
            $rows[] = [
                $hit->getTable(),
                $matched?->getColumn() ?? '',
                $hit->getId(),
                $this->flat($hit->getTitle() ?? ''),
                $this->flat($matched?->getContentWithFixes('', '', $caseSensitive) ?? ''),
                $hit->getReadOnlyReason() ?? '',
            ];
        }

        $this->renderTable($output, $headers, $rows);
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    protected function renderTable(OutputInterface $output, array $headers, array $rows): void
    {
        $buffer = new BufferedOutput(
            $output->getVerbosity(),
            $output->isDecorated(),
            $output->getFormatter(),
        );
        $table = new Table($buffer);
        // Colour tags become ANSI bytes. Terminals that count those bytes wrap
        // the header while the body still fits, so the last heading drops a line.
        $style = clone Table::getStyleDefinition('default');
        $style->setCellHeaderFormat('%s');
        $table->setStyle($style);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $this->fit($table, $headers, $rows);
        $table->render();
        $this->emit($output, $buffer->fetch());
    }

    /**
     * Keep the table inside the terminal and wrap cells that would overflow it.
     * Long text shrinks before table names, column names, and ids.
     *
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    private function fit(Table $table, array $headers, array $rows): void
    {
        foreach ($this->columnCaps($headers, $rows, $this->terminalWidth()) as $index => $cap) {
            $table->setColumnMaxWidth($index, $cap);
        }
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     *
     * @return array<int, int>
     */
    private function columnCaps(array $headers, array $rows, int $terminalWidth): array
    {
        $count = count($headers);

        if ($count === 0) {
            return [];
        }

        // Leave one column free. A line that fills the terminal wraps before its newline.
        $budget = max($count, $terminalWidth - ($count * 3 + 1) - 1);
        $content = [];

        foreach ($headers as $index => $header) {
            $content[$index] = max(1, Helper::width($header));
        }

        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                if (!isset($content[$index])) {
                    continue;
                }

                $content[$index] = max($content[$index], $this->cellWidth($cell));
            }
        }

        $caps = $content;
        $this->shrinkTo($caps, $headers, $budget, [
            'Snippet' => 24,
            'Preview' => 24,
            'Title' => 16,
            'Replacement' => 12,
        ]);
        $this->shrinkTo($caps, $headers, $budget, []);
        $this->giveSpare($caps, $content, $headers, $budget);

        return $caps;
    }

    /**
     * @param array<int, int> $caps
     * @param list<string> $headers
     * @param array<string, int> $floors
     */
    private function shrinkTo(array &$caps, array $headers, int $budget, array $floors): void
    {
        foreach (['Snippet', 'Preview', 'Title', 'Replacement', 'Reason', 'Table', 'Column', 'ID'] as $name) {
            $index = array_search($name, $headers, true);

            if ($index === false || !isset($caps[$index])) {
                continue;
            }

            $floor = max(Helper::width($name), $floors[$name] ?? 0);

            while ($caps[$index] > $floor && array_sum($caps) > $budget) {
                $caps[$index]--;
            }
        }
    }

    /**
     * @param array<int, int> $caps
     * @param array<int, int> $content
     * @param list<string> $headers
     */
    private function giveSpare(array &$caps, array $content, array $headers, int $budget): void
    {
        $spare = $budget - array_sum($caps);

        foreach (['Snippet', 'Preview', 'Title', 'Replacement', 'Reason', 'Table', 'Column'] as $name) {
            if ($spare <= 0) {
                return;
            }

            $index = array_search($name, $headers, true);

            if ($index === false || !isset($caps[$index], $content[$index])) {
                continue;
            }

            $add = min($spare, max(0, $content[$index] - $caps[$index]));
            $caps[$index] += $add;
            $spare -= $add;
        }
    }

    private function cellWidth(string $value): int
    {
        $width = 0;
        $lines = preg_split("/\r\n|\n|\r/", $value);

        foreach (is_array($lines) ? $lines : [$value] as $line) {
            $width = max($width, Helper::width($line));
        }

        return $width;
    }

    protected function terminalWidth(): int
    {
        $width = (new Terminal())->getWidth();

        return $width > 0 ? $width : 80;
    }

    /**
     * Write rendered text, or page it on a terminal when it is taller than the screen.
     */
    protected function emit(OutputInterface $output, string $rendered): void
    {
        if ($this->page($output, $rendered)) {
            return;
        }

        $output->write($rendered, false, OutputInterface::OUTPUT_RAW);
    }

    private function page(OutputInterface $output, string $rendered): bool
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

        $lines = explode("\n", rtrim($rendered, "\n"));

        if (count($lines) <= $room) {
            return false;
        }

        if ($this->pageWithPager($stream, $rendered)) {
            return true;
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

    /**
     * @param resource $stream
     */
    private function pageWithPager($stream, string $rendered): bool
    {
        $program = $this->pagerProgram();

        if ($program === null || !function_exists('proc_open')) {
            return false;
        }

        $path = tempnam(sys_get_temp_dir(), 'fr-page');

        if ($path === false || file_put_contents($path, $rendered) === false) {
            if (is_string($path)) {
                @unlink($path);
            }

            return false;
        }

        $command = str_ends_with($program, 'less')
            ? [$program, '-FRX', '--', $path]
            : [$program, '-e', $path];
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => STDIN,
                1 => $stream,
                2 => $stream,
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            @unlink($path);

            return false;
        }

        proc_close($process);
        @unlink($path);

        return true;
    }

    private function pagerProgram(): ?string
    {
        $path = getenv('PATH');

        if (!is_string($path) || $path === '') {
            $path = '/usr/bin:/bin';
        }

        foreach (['less', 'more'] as $name) {
            foreach (explode(PATH_SEPARATOR, $path) as $dir) {
                if ($dir === '') {
                    continue;
                }

                $candidate = rtrim($dir, '/') . '/' . $name;

                if (is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    protected function confirmed(InputInterface $input, OutputInterface $output): bool
    {
        $helper = $this->getHelper('question');

        if (!$helper instanceof QuestionHelper) {
            throw new \RuntimeException('Question helper is not available');
        }

        $question = new ConfirmationQuestion('Apply these replacements? [y/N] ', false);

        return (bool) $helper->ask($input, $output, $question);
    }

    protected function summary(
        OutputInterface $output,
        int $applied,
        int $skipped,
        int $failed,
        ?string $runId,
    ): void {
        $output->writeln('Applied: <info>' . $applied . '</info>');
        $output->writeln('Skipped: <info>' . $skipped . '</info>');
        $output->writeln('Failed: <info>' . $failed . '</info>');

        if ($runId !== null) {
            $output->writeln('Run id: <comment>' . $runId . '</comment>');
        }
    }

    protected function engine(): Engine
    {
        $engine = Factory::service('Engine', Constants::MODULE_SLUG);

        if (!$engine instanceof Engine) {
            throw new \RuntimeException('Find-replace engine is not available');
        }

        return $engine;
    }

    protected function schema(): Schema
    {
        $schema = Factory::service('Schema', Constants::MODULE_SLUG);

        if (!$schema instanceof Schema) {
            throw new \RuntimeException('Find-replace schema is not available');
        }

        return $schema;
    }

    protected function changes(): Change
    {
        $changes = Factory::model('Change', Constants::MODULE_SLUG);

        if (!$changes instanceof Change) {
            throw new \RuntimeException('Find-replace change model is not available');
        }

        return $changes;
    }

    protected function database(): PDODatabase
    {
        $database = Factory::service('PDODatabase');

        if (!$database instanceof PDODatabase) {
            throw new \RuntimeException('Database is not available');
        }

        return $database;
    }

    protected function flat(string $value): string
    {
        $flat = preg_replace('/\s+/u', ' ', $value);

        return trim(is_string($flat) ? $flat : $value);
    }

    private function flag(InputInterface $input, string $name): bool
    {
        return (bool) $input->getOption($name);
    }

    /**
     * @return list<string>
     */
    private function entries(InputInterface $input, string $name): array
    {
        $value = $input->getOption($name);

        if (!is_array($value)) {
            throw new \InvalidArgumentException('--' . $name . ' must be a list');
        }

        $entries = [];

        foreach ($value as $entry) {
            if (!is_string($entry) || $entry === '') {
                throw new \InvalidArgumentException('--' . $name . ' entries must be strings');
            }

            Pattern::assertValid($entry);
            $entries[] = $entry;
        }

        return $entries;
    }

    private function limit(InputInterface $input): ?int
    {
        $value = $input->getOption('limit');

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('--limit must be a whole number');
    }

    private function contextLength(InputInterface $input): int
    {
        $value = $input->getOption('context-length');

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('--context-length must be a whole number');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function facets(InputInterface $input): array
    {
        $value = $input->getOption('facet');

        if (!is_array($value)) {
            throw new \InvalidArgumentException('--facet must be a list');
        }

        $facets = [];

        foreach ($value as $entry) {
            if (!is_string($entry) || !str_contains($entry, ':')) {
                throw new \InvalidArgumentException('--facet must be key:value');
            }

            [$key, $facetValue] = explode(':', $entry, 2);

            if ($key === '' || $facetValue === '') {
                throw new \InvalidArgumentException('--facet must be key:value');
            }

            $facets[] = [$key, $facetValue];
        }

        return $facets;
    }
}
