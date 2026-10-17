<?php

namespace Nails\FindReplace\Service;

use Nails\Common\Service\PDODatabase;
use Nails\FindReplace\Factory\ChangeRecord;
use Nails\FindReplace\Model\Change;
use Nails\FindReplace\Model\Result;
use Nails\FindReplace\Model\Search;
use Nails\FindReplace\Resource;

class Access
{
    public function __construct(
        private readonly Search $searches,
        private readonly Result $results,
        private readonly Change $changes,
        private readonly PDODatabase $database,
    ) {
    }

    public function search(int $id, int $userId): ?Resource\Search
    {
        return $this->searches->owned($id, $userId);
    }

    public function change(int $id, int $userId): ?ChangeRecord
    {
        return $this->changes->findForUser($this->database, $id, $userId);
    }

    /**
     * Every change in the run must belong to this user and come from the admin.
     *
     * @return list<ChangeRecord>
     */
    public function run(string $runId, int $userId): array
    {
        $rows = $this->changes->forRun($this->database, $runId);

        if ($rows === []) {
            return [];
        }

        foreach ($rows as $row) {
            if ($row->getUserId() !== $userId || $row->getSource() !== \Nails\FindReplace\Factory\Run::SOURCE_ADMIN) {
                return [];
            }
        }

        return $rows;
    }

    /**
     * Result ids for a search this user owns.
     * A requested id from another search returns null. Facet filters drop the rest.
     *
     * @param array<string, string> $facets
     * @param list<int>|null $onlyIds
     *
     * @return list<int>|null
     */
    public function resultIds(int $searchId, int $userId, array $facets, ?array $onlyIds): ?array
    {
        if ($this->search($searchId, $userId) === null) {
            return null;
        }

        return $this->results->ids($searchId, $facets, $onlyIds);
    }
}
