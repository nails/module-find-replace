<?php

namespace Nails\FindReplace\Service;

use Nails\Common\Service\PDODatabase;
use Nails\FindReplace\Exclusion\Pattern;
use Nails\FindReplace\Factory\ChangeRecord;
use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Factory\Location;
use Nails\FindReplace\Factory\Matched;
use Nails\FindReplace\Factory\Outcome;
use Nails\FindReplace\Factory\Query;
use Nails\FindReplace\Factory\Run;
use Nails\FindReplace\Factory\Term;
use Nails\FindReplace\Model\Change;
use Nails\FindReplace\Reason;
use Nails\FindReplace\Scope;
use Nails\FindReplace\Resolver\TableColumn;
use Nails\FindReplace\Serialised;

class Engine
{
    const OPERATOR_CONTROL_CHARACTER = '\\';

    public function __construct(
        private readonly Schema $schema,
        private readonly PDODatabase $database,
    ) {
    }

    /**
     * @return list<Hit>
     */
    public function search(Query $query): array
    {
        foreach ([...$query->getTables(), ...$query->getExcludeTables()] as $entry) {
            Pattern::assertValid($entry);
        }

        $term = new Term($query->getTerm());
        $byTable = [];

        foreach ($this->schema->locations() as $location) {
            if (!$this->inScope($location, $query)) {
                continue;
            }

            $byTable[$location->getTable()][] = $location;
        }

        $results = [];

        foreach ($byTable as $table => $locations) {
            $matches = $this->matches($table, $locations, $term, $query);

            if ($matches === []) {
                continue;
            }

            $results = array_merge($results, $this->resolve($table, $matches));

            if ($query->getLimit() !== null && count($results) >= $query->getLimit()) {
                break;
            }
        }

        if ($query->getLimit() !== null) {
            return array_slice($results, 0, $query->getLimit());
        }

        return $results;
    }

    /**
     * Re-reads each cell and re-checks the current exclusions before writing.
     * Applied rows are recorded on $changes in the same transaction as the update.
     *
     * @param iterable<Hit> $hits
     *
     * @return list<Outcome>
     */
    public function replace(
        iterable $hits,
        string $replacement,
        bool $caseSensitive = true,
        ?Change $changes = null,
        ?Run $run = null,
    ): array {
        if (($changes === null) !== ($run === null)) {
            throw new \InvalidArgumentException('Change rows are written with a replace run');
        }

        $outcomes = [];

        foreach ($hits as $hit) {
            $outcomes[] = $this->replaceHit($hit, $replacement, $caseSensitive, $changes, $run);
        }

        return $outcomes;
    }

    /**
     * Restores value_before where the live cell still equals value_after.
     * Each restored row is recorded as a new change on $run.
     *
     * @return list<Outcome>
     */
    public function revert(string $runId, Change $changes, Run $run): array
    {
        $outcomes = [];

        foreach ($changes->forRun($this->database, $runId) as $row) {
            $outcomes[] = $this->revertRow($row, $changes, $run);
        }

        return $outcomes;
    }

    /**
     * @param list<Location> $locations
     *
     * @return list<Matched>
     */
    private function matches(string $table, array $locations, Term $term, Query $query): array
    {
        $sql = [];
        $parameters = [];
        $seen = [];
        $index = 0;

        foreach ($locations as $location) {
            foreach ($term->getVariants() as $variant) {
                $variantTerm = $variant->getTerm();
                $dedupeKey = $location->getColumn() . "\0" . (
                    $query->isCaseSensitive()
                        ? $variantTerm
                        : mb_strtolower($variantTerm)
                );

                if (isset($seen[$dedupeKey])) {
                    continue;
                }

                $seen[$dedupeKey] = true;
                $primaryKey = $location->getPrimaryKey();
                $idSql = $primaryKey === null ? "''" : $this->quote($primaryKey);

                $sql[] = '
                    SELECT
                        :table_' . $index . ' AS sTable,
                        :column_' . $index . ' AS sColumn,
                        :encoding_' . $index . ' AS sEncoding,
                        :term_' . $index . ' AS sTerm,
                        ' . $idSql . ' AS sId,
                        ' . $this->quote($location->getColumn()) . ' AS sContent
                    FROM ' . $this->quote($table) . '
                    WHERE ' . $this->quote($location->getColumn()) . '
                        LIKE ' . ($query->isCaseSensitive() ? 'BINARY ' : '') . ':variant_' . $index . '
                        ESCAPE ' . $this->escapeClause() . '
                ';

                $parameters['table_' . $index] = $table;
                $parameters['column_' . $index] = $location->getColumn();
                $parameters['encoding_' . $index] = $variant->getEncoding();
                $parameters['term_' . $index] = $variantTerm;
                $parameters['variant_' . $index] = $this->likePattern($variantTerm);
                $index++;
            }
        }

        if ($sql === []) {
            return [];
        }

        $statement = $this->database->prepare(implode(' UNION ALL ', $sql));
        $statement->execute($parameters);
        $matches = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $content = $row['sContent'] ?? null;
            $encoding = $row['sEncoding'] ?? null;
            $matchedTerm = $row['sTerm'] ?? null;
            $id = $row['sId'] ?? null;
            $column = $row['sColumn'] ?? null;

            if (!is_string($content) || !is_string($encoding) || !is_string($matchedTerm) || !is_string($column)) {
                continue;
            }

            $location = $this->locationFor($locations, $column);

            if ($location === null) {
                continue;
            }

            $matches[] = new Matched(
                $table,
                $column,
                $encoding,
                $matchedTerm,
                is_scalar($id) ? (string) $id : '',
                $content,
                $location,
                Serialised::is($content),
                $query->getContextLength(),
            );
        }

        return $matches;
    }

    /**
     * The first resolver that lists the table describes its rows.
     * Primary keys it does not return use the table.column fallback.
     *
     * @param list<Matched> $matches
     *
     * @return list<Hit>
     */
    private function resolve(string $table, array $matches): array
    {
        $resolved = [];

        foreach ($this->schema->registry()->resolvers() as $resolver) {
            if (!in_array($table, $resolver->tables(), true)) {
                continue;
            }

            $resolved = $resolver->resolve($table, $matches);
            break;
        }

        $fallback = new TableColumn();
        $hits = [];

        foreach ($matches as $match) {
            $id = $match->getId();
            $prototype = $resolved[$id] ?? null;

            if (!$prototype instanceof Hit) {
                $created = $fallback->resolve($table, [$match]);
                $prototype = $created[$id] ?? new Hit(
                    $table,
                    $id,
                    title: $table . '.' . $match->getColumn() . ' #' . $id,
                );
            }

            $location = $match->getLocation();
            $serialised = $match->isSerialised();
            $readOnly = $serialised || ($location !== null && $location->isReadOnly());
            $reason = null;

            if ($serialised) {
                $reason = Reason::SERIALISED;
            } elseif ($location !== null && $location->isReadOnly()) {
                $reason = $location->getReadOnlyReason();
            }

            $hits[] = $prototype->withMatch($match, $readOnly, $reason);
        }

        return $hits;
    }

    private function replaceHit(
        Hit $hit,
        string $replacement,
        bool $caseSensitive,
        ?Change $changes,
        ?Run $run,
    ): Outcome {
        $opened = $this->open($hit);

        if ($opened instanceof Outcome) {
            return $opened;
        }

        [$location, $matched] = $opened;

        if ($changes !== null && $run !== null) {
            return $this->commitReplacement(
                $hit,
                $location,
                $matched,
                $replacement,
                $caseSensitive,
                $changes,
                $run,
            );
        }

        try {
            $content = $this->readCell($location, $matched->getId(), false);
        } catch (\PDOException $exception) {
            return new Outcome(Outcome::FAILED, $hit, $exception->getMessage());
        }

        $decision = $this->assess($hit, $matched, $content, $replacement, $caseSensitive);

        if ($decision instanceof Outcome) {
            return $decision;
        }

        try {
            $this->updateCell($location, $matched->getId(), $decision['after']);
        } catch (\PDOException $exception) {
            return new Outcome(Outcome::FAILED, $hit, $exception->getMessage());
        }

        return new Outcome(Outcome::APPLIED, $hit, null, $decision['before'], $decision['after']);
    }

    private function commitReplacement(
        Hit $hit,
        Location $location,
        Matched $matched,
        string $replacement,
        bool $caseSensitive,
        Change $changes,
        Run $run,
    ): Outcome {
        $transaction = $this->database->transaction();
        $transaction->start();

        try {
            $content = $this->readCell($location, $matched->getId(), true);
            $decision = $this->assess($hit, $matched, $content, $replacement, $caseSensitive);

            if ($decision instanceof Outcome) {
                $transaction->rollback();

                return $decision;
            }

            $this->updateCell($location, $matched->getId(), $decision['after']);
            $changes->record(
                $this->database,
                $run,
                $location->getTable(),
                $matched->getId(),
                $location->getColumn(),
                $matched->getEncoding(),
                $decision['term'],
                $decision['replacement'],
                $decision['before'],
                $decision['after'],
            );
            $transaction->commit();

            return new Outcome(Outcome::APPLIED, $hit, null, $decision['before'], $decision['after']);
        } catch (\Throwable $exception) {
            if ($transaction->status()) {
                $transaction->rollback();
            }

            return new Outcome(Outcome::FAILED, $hit, $exception->getMessage());
        }
    }

    private function revertRow(ChangeRecord $row, Change $changes, Run $run): Outcome
    {
        $hit = new Hit(
            $row->getTable(),
            $row->getPrimaryKey(),
            title: $row->getTable() . '.' . $row->getColumn() . ' #' . $row->getPrimaryKey(),
        );
        $location = $this->schema->location($row->getTable(), $row->getColumn());

        if ($location === null) {
            return new Outcome(Outcome::SKIPPED, $hit, Reason::EXCLUDED);
        }

        if ($location->isReadOnly()) {
            return new Outcome(
                Outcome::SKIPPED,
                $hit,
                $location->getReadOnlyReason() ?? Reason::READ_ONLY,
            );
        }

        if ($location->getPrimaryKey() === null) {
            return new Outcome(Outcome::SKIPPED, $hit, Reason::NO_PRIMARY_KEY);
        }

        $transaction = $this->database->transaction();
        $transaction->start();

        try {
            $content = $this->readCell($location, $row->getPrimaryKey(), true);

            if ($content === false) {
                $transaction->rollback();

                return new Outcome(Outcome::SKIPPED, $hit, Reason::MISSING);
            }

            // Equality is the whole cell. A serialised value that is still the
            // recorded value_after is restored as bytes, not edited in place.
            if ($content !== $row->getValueAfter()) {
                $transaction->rollback();

                return new Outcome(Outcome::SKIPPED, $hit, Reason::CHANGED);
            }

            $this->updateCell($location, $row->getPrimaryKey(), $row->getValueBefore());
            $changes->record(
                $this->database,
                $run,
                $location->getTable(),
                $row->getPrimaryKey(),
                $location->getColumn(),
                $row->getEncoding(),
                $row->getTerm(),
                $row->getReplacement(),
                $content,
                $row->getValueBefore(),
                $row->getSearchId(),
            );
            $transaction->commit();

            return new Outcome(Outcome::APPLIED, $hit, null, $content, $row->getValueBefore());
        } catch (\Throwable $exception) {
            if ($transaction->status()) {
                $transaction->rollback();
            }

            return new Outcome(Outcome::FAILED, $hit, $exception->getMessage());
        }
    }

    /**
     * @return Outcome|array{0: Location, 1: Matched}
     */
    private function open(Hit $hit): Outcome|array
    {
        $matched = $hit->getMatched();

        if ($matched === null) {
            return new Outcome(Outcome::FAILED, $hit, 'match is missing');
        }

        $location = $this->schema->location($matched->getTable(), $matched->getColumn());

        if ($location === null) {
            return new Outcome(Outcome::SKIPPED, $hit, Reason::EXCLUDED);
        }

        if ($location->isReadOnly()) {
            return new Outcome(
                Outcome::SKIPPED,
                $hit,
                $location->getReadOnlyReason() ?? Reason::READ_ONLY,
            );
        }

        if ($location->getPrimaryKey() === null) {
            return new Outcome(Outcome::SKIPPED, $hit, Reason::NO_PRIMARY_KEY);
        }

        return [$location, $matched];
    }

    /**
     * @return Outcome|array{before: string, after: string, term: string, replacement: string}
     */
    private function assess(
        Hit $hit,
        Matched $matched,
        string|false $content,
        string $replacement,
        bool $caseSensitive,
    ): Outcome|array {
        if ($content === false) {
            return new Outcome(Outcome::SKIPPED, $hit, Reason::MISSING);
        }

        if (Serialised::is($content)) {
            return new Outcome(Outcome::SKIPPED, $hit, Reason::SERIALISED);
        }

        $needle = $matched->getTerm();
        $found = $caseSensitive
            ? mb_strpos($content, $needle)
            : mb_stripos($content, $needle);

        if ($found === false) {
            return new Outcome(Outcome::SKIPPED, $hit, Reason::STALE);
        }

        try {
            $encoded = (new Term($replacement))->getVariant($matched->getEncoding())->getTerm();
        } catch (\DomainException $exception) {
            return new Outcome(Outcome::FAILED, $hit, $exception->getMessage());
        }

        return [
            'before' => $content,
            'after' => $this->replaceTerm($content, $needle, $encoded, $caseSensitive),
            'term' => $needle,
            'replacement' => $encoded,
        ];
    }

    private function readCell(Location $location, string $id, bool $lock): string|false
    {
        $primaryKey = $location->getPrimaryKey();

        if ($primaryKey === null) {
            return false;
        }

        $select = $this->database->prepare(
            'SELECT ' . $this->quote($location->getColumn())
            . ' FROM ' . $this->quote($location->getTable())
            . ' WHERE ' . $this->quote($primaryKey) . ' = :id'
            . ($lock ? ' FOR UPDATE' : '')
        );
        $select->execute(['id' => $id]);
        $content = $select->fetchColumn();

        return $content === false ? false : (string) $content;
    }

    private function updateCell(Location $location, string $id, string $content): void
    {
        $primaryKey = $location->getPrimaryKey();

        if ($primaryKey === null) {
            throw new \RuntimeException('Cannot update a row without a primary key');
        }

        $update = $this->database->prepare(
            'UPDATE ' . $this->quote($location->getTable())
            . ' SET ' . $this->quote($location->getColumn()) . ' = :content'
            . ' WHERE ' . $this->quote($primaryKey) . ' = :id'
        );
        $update->execute([
            'content' => $content,
            'id' => $id,
        ]);
    }

    private function replaceTerm(
        string $haystack,
        string $needle,
        string $replacement,
        bool $caseSensitive,
    ): string {
        if ($needle === '') {
            return $haystack;
        }

        $needleLength = mb_strlen($needle);
        $return = '';

        while (true) {
            $position = $caseSensitive
                ? mb_strpos($haystack, $needle)
                : mb_stripos($haystack, $needle);

            if ($position === false) {
                break;
            }

            $return .= mb_substr($haystack, 0, $position) . $replacement;
            $haystack = mb_substr($haystack, $position + $needleLength);
        }

        return $return . $haystack;
    }

    private function inScope(Location $location, Query $query): bool
    {
        return Scope::includes(
            $location->getTable(),
            $location->getColumn(),
            $query->getTables(),
            $query->getExcludeTables(),
        );
    }

    /**
     * @param list<Location> $locations
     */
    private function locationFor(array $locations, string $column): ?Location
    {
        foreach ($locations as $location) {
            if ($location->getColumn() === $column) {
                return $location;
            }
        }

        return null;
    }

    private function likePattern(string $term): string
    {
        return '%' . str_replace(
            [
                self::OPERATOR_CONTROL_CHARACTER,
                '%',
                '_',
            ],
            [
                self::OPERATOR_CONTROL_CHARACTER . self::OPERATOR_CONTROL_CHARACTER,
                self::OPERATOR_CONTROL_CHARACTER . '%',
                self::OPERATOR_CONTROL_CHARACTER . '_',
            ],
            $term,
        ) . '%';
    }

    private function escapeClause(): string
    {
        // The control character is a backslash. MySQL reads `\\` inside this
        // quoted ESCAPE literal as that single backslash.
        return '"' . addcslashes(self::OPERATOR_CONTROL_CHARACTER, '\\"') . '"';
    }

    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
