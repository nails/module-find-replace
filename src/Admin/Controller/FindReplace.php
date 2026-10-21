<?php

namespace Nails\FindReplace\Admin\Controller;

use Nails\Admin\Controller\Base;
use Nails\Admin\Factory\Nav;
use Nails\Admin\Helper;
use Nails\Common\Service\Input;
use Nails\Common\Service\Output;
use Nails\Common\Service\PDODatabase;
use Nails\Factory;
use Nails\FindReplace\Admin\OutcomeNotice;
use Nails\FindReplace\Admin\Permission;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Exclusion\Pattern;
use Nails\FindReplace\Factory\Outcome;
use Nails\FindReplace\Factory\Run;
use Nails\FindReplace\Factory\StoredResult;
use Nails\FindReplace\Model\Change;
use Nails\FindReplace\Model\Result;
use Nails\FindReplace\Model\Search;
use Nails\FindReplace\Output\Csv;
use Nails\FindReplace\Queue\Task\Search as SearchTask;
use Nails\FindReplace\Resource;
use Nails\FindReplace\Service\Access;
use Nails\FindReplace\Service\Engine;
use Nails\FindReplace\Service\Registry;
use Nails\Queue\Interface\Data;

class FindReplace extends Base
{
    const PER_PAGE = 50;

    public static function announce(): Nav|array|null
    {
        if (!userHasPermission(Permission\Search::class)) {
            return null;
        }

        /** @var Nav $nav */
        $nav = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
        $nav->setLabel('Utilities');
        $nav->addAction('Find & Replace', 'index');
        $nav->addAction('My changes', 'changes');

        return $nav;
    }

    public function index(): void
    {
        $this->requireSearchPermission();
        $this
            ->addBreadcrumb('Utilities')
            ->addBreadcrumb('Find & Replace')
            ->loadView('index');
    }

    public function create(): void
    {
        $this->requireSearchPermission();
        $input = $this->input();

        if ($input->post() === false || $input->post() === null || $input->post() === []) {
            redirect(self::url());
        }

        try {
            $include = $this->tableEntries($input, 'include_tables');
            $exclude = $this->tableEntries($input, 'exclude_tables');
            $id = $this->searches()->open(
                $this->userId(),
                $this->requiredTerm($input),
                $this->text($input, 'replacement'),
                $this->caseSensitive($input),
                $include,
                $exclude,
            );
            $this->queue($id);
        } catch (\InvalidArgumentException $exception) {
            $this->oUserFeedback->error($exception->getMessage());
            redirect(self::url());

            return;
        }

        $this->oUserFeedback->success('Search queued.');
        redirect(self::url('results') . '?id=' . $id);
    }

    public function status(): void
    {
        $this->requireSearchPermission();
        $search = $this->requireOwned();
        $payload = [
            'status' => $search->status(),
            'tables_scanned' => $search->tablesScanned(),
            'tables_total' => $search->tablesTotal(),
            'result_count' => $search->resultCount(),
            'error' => $search->errorMessage(),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->output()
            ->setContentType('application/json')
            ->setOutput($json);
    }

    public function results(): void
    {
        $this->requireSearchPermission();
        $search = $this->requireOwned();

        try {
            $facets = $this->facets($this->input());
        } catch (\InvalidArgumentException $exception) {
            $this->oUserFeedback->error($exception->getMessage());
            $facets = [];
        }

        $page = $this->page($this->input());
        $total = 0;
        $outstanding = 0;
        $rows = [];

        if ($search->status() === Search::STATUS_COMPLETE) {
            $outstanding = $this->resultModel()->outstanding((int) $search->id);
            $total = $this->resultModel()->count((int) $search->id, $facets);
            $rows = $this->resultModel()->page(
                (int) $search->id,
                $facets,
                $page,
                self::PER_PAGE,
                $search->contextLength(),
            );
        }

        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($search->status() === Search::STATUS_COMPLETE) {
            $exportQuery = ['id' => (int) $search->id];

            if ($facets !== []) {
                $exportQuery['facet'] = $facets;
            }

            Helper::addHeaderButton(
                self::url('export') . '?' . http_build_query($exportQuery),
                'Export CSV',
                'default',
            );
        }

        $this
            ->addBreadcrumb('Utilities')
            ->addBreadcrumb('Find & Replace', self::url())
            ->addBreadcrumb($search->term())
            ->setData('search', $search)
            ->setData('rows', $rows)
            ->setData('facets', $facets)
            ->setData('facetOptions', $this->facetOptions((int) $search->id))
            ->setData('badgeKeys', $this->registry()->badges())
            ->setData('outstanding', $outstanding)
            ->setData('page', min($page, $pages))
            ->setData('pages', $pages)
            ->setData('total', $total)
            ->setData('canReplace', userHasPermission(Permission\Replace::class))
            ->loadView('results');
    }

    public function export(): void
    {
        $this->requireSearchPermission();
        $search = $this->requireOwned();

        try {
            $facets = $this->facets($this->input());
        } catch (\InvalidArgumentException $exception) {
            $this->oUserFeedback->error($exception->getMessage());
            redirect(self::url('results') . '?id=' . (int) $search->id);

            return;
        }

        $ids = $this->access()->resultIds((int) $search->id, $this->userId(), $facets, null);

        if ($ids === null) {
            show404();
            exit;
        }

        $stored = $this->resultModel()->forIds((int) $search->id, $ids, $search->contextLength());
        $hits = array_map(static fn(StoredResult $row): \Nails\FindReplace\Factory\Hit => $row->getHit(), $stored);
        $path = tempnam(sys_get_temp_dir(), 'find-replace-');

        if ($path === false) {
            throw new \RuntimeException('Unable to build CSV output');
        }

        try {
            (new Csv())->write($path, $hits, $search->isCaseSensitive());
            $csv = file_get_contents($path);
        } finally {
            unlink($path);
        }

        if (!is_string($csv)) {
            throw new \RuntimeException('Unable to build CSV output');
        }

        $this->output()
            ->setContentType('text/csv')
            ->setHeader('Content-Disposition: attachment; filename="find-replace.csv"')
            ->setOutput($csv);
    }

    public function replace(): void
    {
        $this->requireReplacePermission();
        $search = $this->requireOwned();
        $input = $this->input();

        try {
            $facets = $this->facets($input);
        } catch (\InvalidArgumentException $exception) {
            $this->oUserFeedback->error($exception->getMessage());
            redirect(self::url('results') . '?id=' . (int) $search->id);

            return;
        }
        $scope = $this->text($input, 'scope');
        $onlyIds = $scope === 'all' ? null : $this->selectedIds($input);
        $ids = $this->access()->resultIds((int) $search->id, $this->userId(), $facets, $onlyIds);

        if ($ids === null) {
            show404();
            exit;
        }

        if ($ids === []) {
            $this->oUserFeedback->error('Nothing to replace.');
            redirect($this->resultsUrl((int) $search->id, $facets));

            return;
        }

        $stored = $this->resultModel()->forIds((int) $search->id, $ids, $search->contextLength());
        $hits = array_map(static fn(StoredResult $row): \Nails\FindReplace\Factory\Hit => $row->getHit(), $stored);
        $outcomes = $this->engine()->replace(
            $hits,
            $search->replacement(),
            $search->isCaseSensitive(),
            $this->changeModel(),
            Run::admin($this->userId(), (int) $search->id),
        );
        $this->report($outcomes);
        redirect($this->resultsUrl((int) $search->id, $facets));
    }

    public function rerun(): void
    {
        $this->requireSearchPermission();
        $search = $this->requireOwned();
        $this->searches()->markPending((int) $search->id);
        $this->resultModel()->clear((int) $search->id);
        $this->queue((int) $search->id);
        $this->oUserFeedback->success('Search queued again.');
        redirect(self::url('results') . '?id=' . (int) $search->id);
    }

    public function persistent(): void
    {
        $this->requireSearchPermission();
        $search = $this->requireOwned();
        $persistent = $this->text($this->input(), 'persistent') === '1';
        $this->searches()->markPersistent((int) $search->id, $persistent);
        $this->oUserFeedback->success($persistent ? 'Search will be kept.' : 'Search will be removed when it expires.');
        redirect(self::url());
    }

    public function delete(): void
    {
        $this->requireSearchPermission();
        $search = $this->requireOwned();

        if (!$this->searches()->delete((int) $search->id)) {
            $this->oUserFeedback->error('The search could not be deleted.');
            redirect(self::url());

            return;
        }

        $this->oUserFeedback->success('Search deleted.');
        redirect(self::url());
    }

    public function changes(): void
    {
        $this->requireSearchPermission();
        $rows = $this->changeModel()->forUser($this->database(), $this->userId());
        $runs = [];

        foreach ($rows as $row) {
            $runs[$row->getRunId()][] = $row;
        }

        $this
            ->addBreadcrumb('Utilities')
            ->addBreadcrumb('Find & Replace', self::url())
            ->addBreadcrumb('My changes')
            ->setData('runs', $runs)
            ->setData('canReplace', userHasPermission(Permission\Replace::class))
            ->loadView('changes');
    }

    public function revert(): void
    {
        $this->requireReplacePermission();
        $input = $this->input();
        $changeId = $this->text($input, 'change_id');
        $runId = $this->text($input, 'run_id');

        if ($changeId !== '' && ctype_digit($changeId)) {
            $row = $this->access()->change((int) $changeId, $this->userId());

            if ($row === null) {
                show404();
                exit;
            }

            $outcomes = [
                $this->engine()->revertChange(
                    $row,
                    $this->changeModel(),
                    Run::admin($this->userId(), $row->getSearchId()),
                ),
            ];
        } elseif ($runId !== '') {
            $rows = $this->access()->run($runId, $this->userId());

            if ($rows === []) {
                show404();
                exit;
            }

            $outcomes = $this->engine()->revert(
                $runId,
                $this->changeModel(),
                Run::admin($this->userId(), $rows[0]->getSearchId()),
            );
        } else {
            $this->oUserFeedback->error('Choose a change to revert.');
            redirect(self::url('changes'));

            return;
        }

        $this->report($outcomes);
        redirect(self::url('changes'));
    }

    private function requireSearchPermission(): void
    {
        if (!userHasPermission(Permission\Search::class)) {
            unauthorised();
            exit;
        }
    }

    private function requireReplacePermission(): void
    {
        $this->requireSearchPermission();

        if (!userHasPermission(Permission\Replace::class)) {
            unauthorised();
            exit;
        }
    }

    private function requireOwned(): Resource\Search
    {
        $id = $this->requestId();
        $search = $this->access()->search($id, $this->userId());

        if (!$search instanceof Resource\Search || $search->id === null) {
            show404();
            exit;
        }

        return $search;
    }

    private function requestId(): int
    {
        $id = $this->text($this->input(), 'id');

        if ($id === '' || !ctype_digit($id)) {
            show404();
            exit;
        }

        return (int) $id;
    }

    private function userId(): int
    {
        $id = activeUser('id');

        if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
            unauthorised();
            exit;
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
     * @return list<string>
     */
    private function tableEntries(Input $input, string $key): array
    {
        $entries = [];

        foreach (preg_split('/\R/', $this->text($input, $key)) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            Pattern::assertValid($line);
            $entries[] = $line;
        }

        return $entries;
    }

    private function requiredTerm(Input $input): string
    {
        $term = $this->text($input, 'term');

        if ($term === '') {
            throw new \InvalidArgumentException('Enter a term.');
        }

        return $term;
    }

    private function caseSensitive(Input $input): bool
    {
        $value = $input->post('case_sensitive');

        if (is_array($value)) {
            return in_array('1', $value, true) || in_array(1, $value, true);
        }

        return $value === '1' || $value === 1 || $value === true;
    }

    private function text(Input $input, string $key): string
    {
        $value = $input->post($key);

        if ($value === false || $value === null) {
            $value = $input->get($key);
        }

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, string>
     */
    private function facets(Input $input): array
    {
        $raw = $input->post('facet');

        if (!is_array($raw)) {
            $raw = $input->get('facet');
        }

        if (!is_array($raw)) {
            return [];
        }

        $facets = [];

        foreach ($raw as $key => $value) {
            if (!is_string($key) || !is_string($value) || $key === '' || $value === '') {
                continue;
            }

            if (!$this->registry()->accepts($key, $value)) {
                throw new \InvalidArgumentException($this->registry()->rejection($key));
            }

            $facets[$key] = $value;
        }

        return $facets;
    }

    private function page(Input $input): int
    {
        $page = $input->get('page');

        if (!is_numeric($page)) {
            return 1;
        }

        return max(1, (int) $page);
    }

    /**
     * @return list<int>
     */
    private function selectedIds(Input $input): array
    {
        $raw = $input->post('result_ids');

        if (!is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $id) {
            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string, string> $facets
     */
    private function resultsUrl(int $id, array $facets, ?int $page = null): string
    {
        $query = ['id' => $id];

        if ($facets !== []) {
            $query['facet'] = $facets;
        }

        if ($page !== null && $page > 1) {
            $query['page'] = $page;
        }

        return self::url('results') . '?' . http_build_query($query);
    }

    /**
     * @return array<string, array{label: string, values: array<string, int>}>
     */
    private function facetOptions(int $searchId): array
    {
        $registry = $this->registry();
        $labels = $registry->facets();
        $counts = $this->resultModel()->countsByFacet($searchId);
        $options = [];

        foreach ($labels as $key => $label) {
            $options[$key] = [
                'label' => $label,
                'values' => $this->acceptedCounts($registry, $key, $counts[$key] ?? []),
            ];
        }

        foreach ($counts as $key => $values) {
            if (!isset($options[$key])) {
                $options[$key] = [
                    'label' => $key,
                    'values' => $this->acceptedCounts($registry, $key, $values),
                ];
            }
        }

        return $options;
    }

    /**
     * @param array<string, int> $counts
     *
     * @return array<string, int>
     */
    private function acceptedCounts(Registry $registry, string $key, array $counts): array
    {
        $accepted = [];

        foreach ($counts as $value => $count) {
            $value = (string) $value;

            if ($registry->accepts($key, $value)) {
                $accepted[$value] = $count;
            }
        }

        return $accepted;
    }

    private function registry(): Registry
    {
        $registry = Factory::service('Registry', Constants::MODULE_SLUG);

        if (!$registry instanceof Registry) {
            throw new \RuntimeException('Find-replace registry is not available');
        }

        return $registry;
    }

    /**
     * @param list<Outcome> $outcomes
     */
    private function report(array $outcomes): void
    {
        $notice = new OutcomeNotice();
        $this->oUserFeedback->success($notice->summary($outcomes));
        $skipped = $notice->skipped($outcomes);
        $failed = $notice->failed($outcomes);

        if ($skipped !== '') {
            $this->oUserFeedback->warning($skipped);
        }

        if ($failed !== '') {
            $this->oUserFeedback->error($failed);
        }
    }

    private function searches(): Search
    {
        $searches = Factory::model('Search', Constants::MODULE_SLUG);

        if (!$searches instanceof Search) {
            throw new \RuntimeException('Find-replace search model is not available');
        }

        return $searches;
    }

    private function resultModel(): Result
    {
        $results = Factory::model('Result', Constants::MODULE_SLUG);

        if (!$results instanceof Result) {
            throw new \RuntimeException('Find-replace result model is not available');
        }

        return $results;
    }

    private function changeModel(): Change
    {
        $changes = Factory::model('Change', Constants::MODULE_SLUG);

        if (!$changes instanceof Change) {
            throw new \RuntimeException('Find-replace change model is not available');
        }

        return $changes;
    }

    private function access(): Access
    {
        $access = Factory::service('Access', Constants::MODULE_SLUG);

        if (!$access instanceof Access) {
            throw new \RuntimeException('Find-replace access checks are not available');
        }

        return $access;
    }

    private function engine(): Engine
    {
        $engine = Factory::service('Engine', Constants::MODULE_SLUG);

        if (!$engine instanceof Engine) {
            throw new \RuntimeException('Find-replace engine is not available');
        }

        return $engine;
    }

    private function database(): PDODatabase
    {
        $database = Factory::service('PDODatabase');

        if (!$database instanceof PDODatabase) {
            throw new \RuntimeException('PDODatabase is not available');
        }

        return $database;
    }

    private function input(): Input
    {
        $input = Factory::service('Input');

        if (!$input instanceof Input) {
            throw new \RuntimeException('Input is not available');
        }

        return $input;
    }

    private function output(): Output
    {
        $output = Factory::service('Output');

        if (!$output instanceof Output) {
            throw new \RuntimeException('Output is not available');
        }

        return $output;
    }
}
