<?php

namespace Nails\FindReplace\Api\Controller;

use Nails\Admin\Traits\Api\RestrictToAdmin;
use Nails\Api\Exception\ApiException;
use Nails\Api\Factory\ApiResponse;
use Nails\Common\Exception\ValidationException;
use Nails\Common\Service\HttpCodes;
use Nails\Factory;
use Nails\FindReplace\Admin\Controller\FindReplace;
use Nails\FindReplace\Admin\Permission;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Exclusion\Pattern;
use Nails\FindReplace\Model\Result;
use Nails\FindReplace\Model\Search as SearchModel;
use Nails\FindReplace\Queue\Task\Search as SearchTask;
use Nails\FindReplace\Resource;
use Nails\Queue\Interface\Data;

class Search extends \Nails\Api\Controller\Base
{
    use RestrictToAdmin;

    public static function requirePermission(): ?string
    {
        return Permission\Search::class;
    }

    public function getRemap(string $method): ApiResponse
    {
        if ($method === 'index') {
            $rows = [];

            foreach ($this->searches()->forUser($this->userId()) as $search) {
                $rows[] = $this->payload($search);
            }

            return $this->respond($rows);
        }

        if (!ctype_digit($method)) {
            throw new ApiException('Search not found', HttpCodes::STATUS_NOT_FOUND);
        }

        return $this->respond($this->payload($this->owned((int) $method)));
    }

    public function postIndex(): ApiResponse
    {
        $input = $this->input();

        try {
            $id = $this->searches()->open(
                $this->userId(),
                $this->requiredTerm($input),
                $this->text($input, 'replacement'),
                $this->flag($input['case_sensitive'] ?? null),
                $this->tableEntries($input['include_tables'] ?? null),
                $this->tableEntries($input['exclude_tables'] ?? null),
            );
            $this->queue($id);
        } catch (\InvalidArgumentException $exception) {
            throw new ValidationException($exception->getMessage());
        }

        return $this->respond($this->payload($this->owned($id)), HttpCodes::STATUS_CREATED);
    }

    public function postRerun(): ApiResponse
    {
        $id = (int) $this->owned($this->requestId($this->input()))->id;
        $this->searches()->markPending($id);
        $this->results()->clear($id);
        $this->queue($id);

        return $this->respond($this->payload($this->owned($id)));
    }

    public function postPersistent(): ApiResponse
    {
        $input = $this->input();
        $id = (int) $this->owned($this->requestId($input))->id;
        $this->searches()->markPersistent($id, $this->flag($input['persistent'] ?? null));

        return $this->respond($this->payload($this->owned($id)));
    }

    public function postDelete(): ApiResponse
    {
        $id = (int) $this->owned($this->requestId($this->input()))->id;

        if (!$this->searches()->delete($id)) {
            throw new ApiException('The search could not be deleted.', HttpCodes::STATUS_BAD_REQUEST);
        }

        return $this->respond(['deleted' => true]);
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function payload(Resource\Search $search): array
    {
        $id = (int) $search->id;

        return [
            'id' => $id,
            'term' => $search->term(),
            'replacement' => $search->replacement(),
            'status' => $search->status(),
            'tables_scanned' => $search->tablesScanned(),
            'tables_total' => $search->tablesTotal(),
            'result_count' => $search->resultCount(),
            'persistent' => $search->isPersistent(),
            'last_run' => $search->lastRunLabel(),
            'error' => $search->errorMessage(),
            'results_url' => FindReplace::url('results') . '?id=' . $id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        $input = [];

        foreach ($this->getRequestData() as $key => $value) {
            if (is_string($key)) {
                $input[$key] = $value;
            }
        }

        return $input;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function requiredTerm(array $input): string
    {
        $term = $this->text($input, 'term');

        if ($term === '') {
            throw new ValidationException('Enter a term.');
        }

        return $term;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function text(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private function flag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return in_array(1, $value, true)
                || in_array('1', $value, true)
                || in_array(true, $value, true);
        }

        return $value === 1 || $value === '1';
    }

    /**
     * @return list<string>
     */
    private function tableEntries(mixed $value): array
    {
        if (is_string($value)) {
            $lines = preg_split('/\R/', $value) ?: [];
        } elseif (is_array($value)) {
            $lines = $value;
        } else {
            $lines = [];
        }

        $entries = [];

        foreach ($lines as $line) {
            if (!is_string($line)) {
                continue;
            }

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            Pattern::assertValid($line);
            $entries[] = $line;
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function requestId(array $input): int
    {
        $id = $input['id'] ?? null;

        if (is_int($id)) {
            return $id;
        }

        if (is_string($id) && ctype_digit($id)) {
            return (int) $id;
        }

        throw new ValidationException('Choose a search.');
    }

    private function owned(int $id): Resource\Search
    {
        $search = $this->searches()->owned($id, $this->userId());

        if (!$search instanceof Resource\Search || $search->id === null) {
            throw new ApiException('Search not found', HttpCodes::STATUS_NOT_FOUND);
        }

        return $search;
    }

    private function userId(): int
    {
        $id = activeUser('id');

        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            throw new ApiException('You are not signed in', HttpCodes::STATUS_UNAUTHORIZED);
        }

        return (int) $id;
    }

    private function queue(int $id): void
    {
        $manager = Factory::service('Manager', \Nails\Queue\Constants::MODULE_SLUG);
        $data = Factory::factory('Data', \Nails\Queue\Constants::MODULE_SLUG, $id);

        if (!method_exists($manager, 'push') || !$data instanceof Data) {
            throw new \RuntimeException('Queue is not available');
        }

        $manager->push(new SearchTask(), $data);
    }

    /**
     * @param mixed $data
     */
    private function respond(mixed $data, int $code = HttpCodes::STATUS_OK): ApiResponse
    {
        $response = Factory::factory('ApiResponse', \Nails\Api\Constants::MODULE_SLUG);

        if (!$response instanceof ApiResponse) {
            throw new \RuntimeException('API response is not available');
        }

        return $response
            ->setCode($code)
            ->setData($data);
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
}
