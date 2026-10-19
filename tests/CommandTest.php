<?php

namespace Tests\FindReplace;

require_once __DIR__ . '/DatabaseTestCase.php';

use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Console\Command\Fields;
use Nails\FindReplace\Console\Command\Replace;
use Nails\FindReplace\Console\Command\Revert;
use Nails\FindReplace\Console\Command\Search;
use Nails\FindReplace\Database\Migration\Migration0;
use Nails\FindReplace\Factory\Run;
use Nails\FindReplace\Reason;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends DatabaseTestCase
{
    /** @var list<string> */
    private array $files = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->pdo()->query('DROP TABLE IF EXISTS `nails_find_replace_change`');
        (new Migration0($this->pdo()))->execute();
        $this->registerTable('nails_find_replace_change');
    }

    public function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function test_fields_lists_discovered_facets_and_tables(): void
    {
        $table = 'fr_cli_fields';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(80) NULL,
            `slug` VARCHAR(80) NULL,
            `token` VARCHAR(80) NULL,
            PRIMARY KEY (`id`)
        ');

        $output = $this->executeCommand(new Fields(), []);
        $display = $output->getDisplay();

        $this->assertSame(0, $output->getStatusCode());
        $this->assertStringContainsString('Facets:', $display);
        $this->assertStringContainsString("Resolver (key: resolver)\n    Accepts any value", $display);
        $this->assertStringContainsString('Pass as --facet=key:value', $display);
        $this->assertStringContainsString('Resolvers:', $display);
        $this->assertStringContainsString('Tables:', $display);
        $this->assertStringContainsString('Pass as --table=name or --table=name.column', $display);
        $this->assertStringContainsString('Re-run with -v', $display);
        $this->assertStringContainsString($table, $display);
        $this->assertDoesNotMatchRegularExpression('/' . $table . '\n    body\s+searchable/', $display);

        $verbose = $this->executeCommand(new Fields(), [], verbosity: OutputInterface::VERBOSITY_VERBOSE);
        $columns = $verbose->getDisplay();

        $this->assertSame(0, $verbose->getStatusCode());
        $this->assertStringNotContainsString('Re-run with -v', $columns);
        $this->assertMatchesRegularExpression(
            '/' . $table . '\n    body\s+searchable\n    slug\s+read-only\s+' . preg_quote(Reason::READ_ONLY, '/') . '/',
            $columns,
        );
        $this->assertSame(1, substr_count($columns, $table));
        $this->assertDoesNotMatchRegularExpression('/\n    token\s+/', $columns);
    }

    public function test_search_wraps_the_results_table_to_the_terminal(): void
    {
        $table = 'fr_cli_wrap';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` TEXT NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, [
            'id' => 1,
            'body' => 'alpha ' . str_repeat('z', 200) . ' endtoken',
        ]);

        putenv('COLUMNS=72');

        try {
            $output = $this->executeCommand(new Search(), [
                'term' => 'alpha',
                '--table' => [$table],
            ], decorated: true);
        } finally {
            putenv('COLUMNS');
        }

        $display = $output->getDisplay();
        $this->assertSame(0, $output->getStatusCode());
        $this->assertMatchesRegularExpression(
            '/\|\s+Table\s+\|\s+Column\s+\|\s+ID\s+\|\s+Title\s+\|\s+Snippet\s+\|\s+Reason\s+\|/',
            $display,
        );
        $this->assertStringContainsString('alpha', $display);
        $this->assertStringContainsString($table, $display);
        $this->assertDoesNotMatchRegularExpression('/z{40}/', $display);

        foreach (explode("\n", $display) as $line) {
            $line = rtrim($line, "\r");
            // Byte length, not just visible width: colour codes must not push the header over.
            $this->assertLessThanOrEqual(72, strlen($line));
        }
    }

    public function test_search_prints_a_table_and_writes_csv_and_json(): void
    {
        $table = 'fr_cli_search';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(80) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha report']);

        $tableOutput = $this->executeCommand(new Search(), [
            'term' => 'alpha',
            '--table' => [$table],
        ]);
        $this->assertSame(0, $tableOutput->getStatusCode());
        $this->assertStringContainsString('Found 1 match', $tableOutput->getDisplay());
        $this->assertStringContainsString($table, $tableOutput->getDisplay());
        $this->assertStringContainsString('alpha report', $tableOutput->getDisplay());

        $csv = $this->temp('csv');
        $csvOutput = $this->executeCommand(new Search(), [
            'term' => 'alpha',
            '--table' => [$table],
            '--format' => 'csv',
            '--output' => $csv,
        ]);
        $this->assertSame(0, $csvOutput->getStatusCode());
        $csvBody = (string) file_get_contents($csv);
        $this->assertStringContainsString('table,column,primary_key', $csvBody);
        $this->assertStringContainsString($table, $csvBody);
        $this->assertStringContainsString('alpha report', $csvBody);

        $json = $this->temp('json');
        $jsonOutput = $this->executeCommand(new Search(), [
            'term' => 'alpha',
            '--table' => [$table],
            '--format' => 'json',
            '--output' => $json,
        ]);
        $this->assertSame(0, $jsonOutput->getStatusCode());
        $decoded = json_decode((string) file_get_contents($json), true);

        if (!is_array($decoded)) {
            $this->fail('Search JSON is not an object');
        }

        $hits = $decoded['hits'] ?? null;

        if (!is_array($hits)) {
            $this->fail('Search JSON has no hits');
        }

        $hit = $hits[0] ?? null;

        if (!is_array($hit)) {
            $this->fail('Search JSON hit is missing');
        }

        $this->assertTrue($decoded['case_sensitive']);
        $this->assertSame('alpha', $decoded['term']);
        $this->assertCount(1, $hits);
        $this->assertSame('1', $hit['primary_key'] ?? null);
        $this->assertSame('plaintext', $hit['encoding'] ?? null);
    }

    public function test_search_applies_case_facet_resolved_and_limit_filters(): void
    {
        $table = 'fr_cli_filter';
        $other = 'fr_cli_filter_other';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(80) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->createTable($other, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(80) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'Alpha one']);
        $this->insert($table, ['id' => 2, 'body' => 'Alpha two']);
        $this->insert($other, ['id' => 1, 'body' => 'Alpha other']);

        $sensitive = $this->executeCommand(new Search(), [
            'term' => 'alpha',
            '--table' => [$table],
        ]);
        $this->assertStringContainsString('Found 0 match', $sensitive->getDisplay());

        $insensitive = $this->executeCommand(new Search(), [
            'term' => 'alpha',
            '--table' => [$table],
            '--case-insensitive' => true,
        ]);
        $this->assertStringContainsString('Found 2 match', $insensitive->getDisplay());

        $limited = $this->executeCommand(new Search(), [
            'term' => 'Alpha',
            '--table' => [$table],
            '--limit' => '1',
        ]);
        $this->assertStringContainsString('Found 1 match', $limited->getDisplay());

        $unresolved = $this->executeCommand(new Search(), [
            'term' => 'Alpha',
            '--table' => [$table],
            '--facet' => ['resolver:Unresolved'],
        ]);
        $this->assertStringContainsString('Found 2 match', $unresolved->getDisplay());

        $anded = $this->executeCommand(new Search(), [
            'term' => 'Alpha',
            '--table' => [$table],
            '--facet' => ['resolver:Unresolved', 'status:PUBLISHED'],
        ]);
        $this->assertStringContainsString('Found 0 match', $anded->getDisplay());

        $archived = $this->executeCommand(new Search(), [
            'term' => 'Alpha',
            '--table' => [$table],
            '--facet' => ['status:ARCHIVED'],
        ]);
        $this->assertNotSame(0, $archived->getStatusCode());
        $this->assertStringContainsString('status must be one of DRAFT, PUBLISHED', $archived->getDisplay());

        $language = $this->executeCommand(new Search(), [
            'term' => 'Alpha',
            '--table' => [$table],
            '--facet' => ['language:FR'],
        ]);
        $this->assertNotSame(0, $language->getStatusCode());
        $this->assertStringContainsString('language must be one of CN, DE, EN, JP', $language->getDisplay());

        $resolved = $this->executeCommand(new Search(), [
            'term' => 'Alpha',
            '--table' => [$table, $other],
            '--resolved-only' => true,
        ]);
        $this->assertStringContainsString('Found 0 match', $resolved->getDisplay());

        $excluded = $this->executeCommand(new Search(), [
            'term' => 'Alpha',
            '--table' => [$table, $other],
            '--exclude-table' => [$other],
        ]);
        $this->assertStringContainsString('Found 2 match', $excluded->getDisplay());
        $this->assertStringNotContainsString($other, $excluded->getDisplay());

        $invalid = $this->executeCommand(new Search(), [
            'term' => 'Alpha',
            '--table' => ['/[/'],
        ]);
        $this->assertSame(1, $invalid->getStatusCode());
        $this->assertStringContainsString('Invalid find-replace exclusion regex', $invalid->getDisplay());
    }

    public function test_replace_dry_run_lists_skip_reasons_and_does_not_write(): void
    {
        $table = 'fr_cli_dry';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` TEXT NULL,
            `slug` VARCHAR(80) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha plain', 'slug' => 'kept']);
        $this->insert($table, ['id' => 2, 'body' => 's:5:"alpha";', 'slug' => 'serial']);
        $this->insert($table, ['id' => 3, 'body' => 'other', 'slug' => 'alpha-slug']);

        $output = $this->executeCommand(new Replace(), [
            'term' => 'alpha',
            'replacement' => 'omega',
            '--table' => [$table],
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $output->getStatusCode());
        $display = $output->getDisplay();
        $this->assertStringContainsString('Would apply: 1', $display);
        $this->assertStringContainsString('Would skip: 2', $display);
        $this->assertStringContainsString(Reason::SERIALISED, $display);
        $this->assertStringContainsString(Reason::READ_ONLY, $display);
        $this->assertStringContainsString('Dry run: no changes written', $display);
        $this->assertStringContainsString('«omega»', $display);
        $this->assertSame('alpha plain', $this->cell($table, 'body', '1'));
        $this->assertSame([], $this->changeRows());
    }

    public function test_replace_yes_writes_the_cell_and_a_change_row(): void
    {
        $table = 'fr_cli_yes';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(80) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha one']);
        $this->insert($table, ['id' => 2, 'body' => 'alpha two']);

        $output = $this->executeCommand(new Replace(), [
            'term' => 'alpha',
            'replacement' => 'omega',
            '--table' => [$table],
            '--yes' => true,
        ]);

        $this->assertSame(0, $output->getStatusCode());
        $this->assertStringNotContainsString('Apply these replacements?', $output->getDisplay());
        $this->assertMatchesRegularExpression('/Run id: [0-9a-f-]{36}/', $output->getDisplay());
        $this->assertSame('omega one', $this->cell($table, 'body', '1'));
        $this->assertSame('omega two', $this->cell($table, 'body', '2'));

        $rows = $this->changeRows();
        $first = null;
        $second = null;

        foreach ($rows as $row) {
            $primaryKey = $row['primary_key'] ?? null;

            if ($primaryKey === '1') {
                $first = $row;
            } elseif ($primaryKey === '2') {
                $second = $row;
            }
        }

        if (!is_array($first) || !is_array($second)) {
            $this->fail('Expected a change row for each replaced cell');
        }

        $this->assertCount(2, $rows);
        $this->assertSame($first['run_id'] ?? null, $second['run_id'] ?? null);
        $this->assertSame(Run::SOURCE_CLI, $first['source'] ?? null);
        $this->assertTrue(array_key_exists('user_id', $first));
        $this->assertTrue(array_key_exists('search_id', $first));
        $this->assertNull($first['user_id']);
        $this->assertNull($first['search_id']);
        $this->assertSame($table, $first['table_name'] ?? null);
        $this->assertSame('body', $first['column_name'] ?? null);
        $this->assertSame('alpha one', $first['value_before'] ?? null);
        $this->assertSame('omega one', $first['value_after'] ?? null);
        $this->assertSame('alpha', $first['term'] ?? null);
        $this->assertSame('omega', $first['replacement'] ?? null);
    }

    public function test_replace_prompt_defaults_to_no_and_yes_applies(): void
    {
        $table = 'fr_cli_prompt';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(80) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha']);

        $declined = $this->executeCommand(new Replace(), [
            'term' => 'alpha',
            'replacement' => 'omega',
            '--table' => [$table],
        ], true, ['n']);
        $this->assertStringContainsString('Apply these replacements? [y/N]', $declined->getDisplay());
        $this->assertStringContainsString('No changes written.', $declined->getDisplay());
        $this->assertSame('alpha', $this->cell($table, 'body', '1'));

        $accepted = $this->executeCommand(new Replace(), [
            'term' => 'alpha',
            'replacement' => 'omega',
            '--table' => [$table],
        ], true, ['y']);
        $this->assertSame(0, $accepted->getStatusCode());
        $this->assertSame('omega', $this->cell($table, 'body', '1'));
    }

    public function test_replace_from_applies_only_the_rows_left_in_the_file(): void
    {
        $table = 'fr_cli_from';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(80) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'gamma stays']);
        $this->insert($table, ['id' => 2, 'body' => 'gamma goes']);

        $json = $this->temp('json');
        $this->executeCommand(new Search(), [
            'term' => 'gamma',
            '--table' => [$table],
            '--format' => 'json',
            '--output' => $json,
        ]);

        $decoded = json_decode((string) file_get_contents($json), true);

        if (!is_array($decoded)) {
            $this->fail('Search JSON is not an object');
        }

        $hits = $decoded['hits'] ?? null;

        if (!is_array($hits)) {
            $this->fail('Search JSON has no hits');
        }

        $kept = [];

        foreach ($hits as $hit) {
            if (is_array($hit) && ($hit['primary_key'] ?? null) === '2') {
                $kept[] = $hit;
            }
        }

        $decoded['hits'] = $kept;
        file_put_contents($json, json_encode($decoded));

        $output = $this->executeCommand(new Replace(), [
            'term' => 'gamma',
            'replacement' => 'delta',
            '--from' => $json,
            '--yes' => true,
        ]);

        $this->assertSame(0, $output->getStatusCode());
        $this->assertStringContainsString('Applied: 1', $output->getDisplay());
        $this->assertSame('gamma stays', $this->cell($table, 'body', '1'));
        $this->assertSame('delta goes', $this->cell($table, 'body', '2'));
    }

    public function test_revert_restores_a_run_and_refuses_a_cell_that_changed(): void
    {
        $table = 'fr_cli_revert';
        $this->createTable($table, '
            `id` INT NOT NULL AUTO_INCREMENT,
            `body` VARCHAR(80) NULL,
            PRIMARY KEY (`id`)
        ');
        $this->insert($table, ['id' => 1, 'body' => 'alpha keep']);
        $this->insert($table, ['id' => 2, 'body' => 'alpha edit']);

        $replaced = $this->executeCommand(new Replace(), [
            'term' => 'alpha',
            'replacement' => 'omega',
            '--table' => [$table],
            '--yes' => true,
        ]);
        $this->assertSame(1, preg_match('/Run id: ([0-9a-f-]{36})/', $replaced->getDisplay(), $matches));
        $runId = $matches[1] ?? null;

        if (!is_string($runId)) {
            $this->fail('Replace did not print a run id');
        }

        $this->pdo()->prepare('UPDATE `' . $table . '` SET `body` = :body WHERE `id` = 2')
            ->execute(['body' => 'hand edited']);

        $output = $this->executeCommand(new Revert(), ['run-id' => $runId]);
        $display = $output->getDisplay();

        $this->assertSame(0, $output->getStatusCode());
        $this->assertStringContainsString('Restored: 1', $display);
        $this->assertStringContainsString('Refused: 1', $display);
        $this->assertStringContainsString(Reason::CHANGED, $display);
        $this->assertStringContainsString($table . '.body #2', $display);
        $this->assertSame('alpha keep', $this->cell($table, 'body', '1'));
        $this->assertSame('hand edited', $this->cell($table, 'body', '2'));

        $rows = $this->changeRows();
        $revertRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['run_id'] !== $runId,
        ));
        $this->assertCount(1, $revertRows);
        $this->assertSame('omega keep', $revertRows[0]['value_before']);
        $this->assertSame('alpha keep', $revertRows[0]['value_after']);
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string> $answers
     */
    private function executeCommand(
        Command $command,
        array $input,
        bool $interactive = false,
        array $answers = [],
        int $verbosity = OutputInterface::VERBOSITY_NORMAL,
        bool $decorated = false,
    ): CommandTester {
        Factory::destroyService('Schema', Constants::MODULE_SLUG);
        Factory::destroyService('Engine', Constants::MODULE_SLUG);
        Factory::destroyModel('Change', Constants::MODULE_SLUG);

        $application = new Application();
        $application->setAutoExit(false);
        $application->add($command);
        $name = $command->getName();

        if ($name === null) {
            throw new \RuntimeException('Command name is not set');
        }

        $command = $application->find($name);
        $tester = new CommandTester($command);

        if ($answers !== []) {
            $tester->setInputs($answers);
        }

        $tester->execute($input, [
            'interactive' => $interactive,
            'verbosity' => $verbosity,
            'decorated' => $decorated,
        ]);

        return $tester;
    }

    private function temp(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fr');

        if ($path === false) {
            throw new \RuntimeException('Unable to create a temp file');
        }

        $renamed = $path . '.' . $extension;
        rename($path, $renamed);
        $this->files[] = $renamed;

        return $renamed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function changeRows(): array
    {
        $rows = $this->pdo()
            ->query('SELECT * FROM `nails_find_replace_change` ORDER BY `id` ASC')
            ->fetchAll(\PDO::FETCH_ASSOC);

        $normalised = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = [];

            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $item[$key] = $value;
                }
            }

            $normalised[] = $item;
        }

        return $normalised;
    }
}
