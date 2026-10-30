<?php

namespace Nails\FindReplace\Queue\Task;

use Nails\Common\Service\Database;
use Nails\Factory;
use Nails\FindReplace\Admin\Url;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Factory\Email\Search\Complete;
use Nails\FindReplace\Factory\Email\Search\Fail;
use Nails\FindReplace\Factory\Query;
use Nails\FindReplace\Model\Result;
use Nails\FindReplace\Model\Search as SearchModel;
use Nails\FindReplace\Resource;
use Nails\FindReplace\Service\Engine;
use Nails\Queue\Interface\Data;
use Nails\Queue\Interface\Task;

class Search implements Task
{
    public static function getMaxRetries(): int
    {
        return 0;
    }

    public function run(Data $data): void
    {
        $id = $this->searchId($data);
        $searches = $this->searches();
        $search = $searches->getById($id);

        if (!$search instanceof Resource\Search) {
            return;
        }

        try {
            $this->execute($searches, $search);
        } catch (\Throwable $exception) {
            $searches->markFailed($id, $exception->getMessage());
            $this->notifyFail($search, $exception->getMessage());

            throw $exception;
        }
    }

    private function execute(SearchModel $searches, Resource\Search $search): void
    {
        $id = (int) $search->id;
        $searches->markRunning($id);
        $query = new Query(
            $search->term(),
            $search->isCaseSensitive(),
            $search->includeTables(),
            $search->excludeTables(),
            null,
            $search->contextLength(),
        );
        $hits = $this->engine()->search(
            $query,
            static function (int $scanned, int $total) use ($searches, $id): void {
                $searches->progress($id, $scanned, $total);
            },
        );
        $this->results()->store($id, $hits, $search->isCaseSensitive());
        $searches->markComplete($id, count($hits));
        $this->notifyComplete($search, count($hits));
    }

    private function searchId(Data $data): int
    {
        $payload = $data->get();

        if (is_int($payload)) {
            return $payload;
        }

        if (is_string($payload) && ctype_digit($payload)) {
            return (int) $payload;
        }

        if (is_object($payload) && property_exists($payload, 'search_id') && is_numeric($payload->search_id)) {
            return (int) $payload->search_id;
        }

        throw new \InvalidArgumentException('Find-replace search job payload must be a search id');
    }

    private function notifyComplete(Resource\Search $search, int $resultCount): void
    {
        try {
            $email = Factory::factory('EmailSearchComplete', Constants::MODULE_SLUG);

            if (!$email instanceof Complete) {
                return;
            }

            $email
                ->to($search->userId())
                ->data([
                    'term' => $search->term(),
                    'result_count' => $resultCount,
                    'results_url' => Url::to('results') . '?id=' . (int) $search->id,
                ])
                ->send();
        } catch (\Throwable) {
            // A finished search stays complete when the notification cannot be sent.
            $this->resetQueryBuilder();
        }
    }

    private function notifyFail(Resource\Search $search, string $error): void
    {
        try {
            $email = Factory::factory('EmailSearchFail', Constants::MODULE_SLUG);

            if (!$email instanceof Fail) {
                return;
            }

            $email
                ->to($search->userId())
                ->data([
                    'term' => $search->term(),
                    'error' => $error,
                ])
                ->send();
        } catch (\Throwable) {
            // The search row already records the failure.
            $this->resetQueryBuilder();
        }
    }

    /**
     * A failed notification query stays on the shared builder and is appended to the next model query.
     */
    private function resetQueryBuilder(): void
    {
        $database = Factory::service('Database');

        if ($database instanceof Database) {
            $database->reset();
        }
    }

    private function searches(): SearchModel
    {
        $searches = Factory::model('Search', Constants::MODULE_SLUG);

        if (!$searches instanceof SearchModel) {
            throw new \RuntimeException('Find-replace search model is not available');
        }

        return $searches;
    }

    private function results(): Result
    {
        $results = Factory::model('Result', Constants::MODULE_SLUG);

        if (!$results instanceof Result) {
            throw new \RuntimeException('Find-replace result model is not available');
        }

        return $results;
    }

    private function engine(): Engine
    {
        $engine = Factory::service('Engine', Constants::MODULE_SLUG);

        if (!$engine instanceof Engine) {
            throw new \RuntimeException('Find-replace engine is not available');
        }

        return $engine;
    }
}
