<?php

use Nails\Common\Service\PDODatabase;
use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Factory\Email\Search\Complete;
use Nails\FindReplace\Factory\Email\Search\Fail;
use Nails\FindReplace\Factory\Query;
use Nails\FindReplace\Factory\Term;
use Nails\FindReplace\Model;
use Nails\FindReplace\Resource;
use Nails\FindReplace\Service\Access;
use Nails\FindReplace\Service\Engine;
use Nails\FindReplace\Service\Registry;
use Nails\FindReplace\Service\Schema;

return [
    'services' => [
        'Registry' => function (?array $resolvers = null): Registry {
            return new Registry(null, $resolvers);
        },
        'Schema' => function (?PDODatabase $database = null, ?Registry $registry = null): Schema {
            $database = $database ?? Factory::service('PDODatabase');
            $registry = $registry ?? Factory::service('Registry', Constants::MODULE_SLUG);

            if (!$database instanceof PDODatabase || !$registry instanceof Registry) {
                throw new \RuntimeException('Find-replace schema dependencies are not available');
            }

            return new Schema($database, $registry);
        },
        'Engine' => function (?PDODatabase $database = null, ?Schema $schema = null): Engine {
            $database = $database ?? Factory::service('PDODatabase');
            $schema = $schema ?? Factory::service('Schema', Constants::MODULE_SLUG);

            if (!$database instanceof PDODatabase || !$schema instanceof Schema) {
                throw new \RuntimeException('Find-replace engine dependencies are not available');
            }

            return new Engine($schema, $database);
        },
        'Access' => function (): Access {
            $searches = Factory::model('Search', Constants::MODULE_SLUG);
            $results = Factory::model('Result', Constants::MODULE_SLUG);
            $changes = Factory::model('Change', Constants::MODULE_SLUG);
            $database = Factory::service('PDODatabase');

            if (
                !$searches instanceof Model\Search
                || !$results instanceof Model\Result
                || !$changes instanceof Model\Change
                || !$database instanceof PDODatabase
            ) {
                throw new \RuntimeException('Find-replace access dependencies are not available');
            }

            return new Access($searches, $results, $changes, $database);
        },
    ],
    'models' => [
        'Search' => function (): Model\Search {
            return new Model\Search();
        },
        'Result' => function (): Model\Result {
            return new Model\Result();
        },
        'ResultFacet' => function (): Model\Result\Facet {
            return new Model\Result\Facet();
        },
        'Change' => function (): Model\Change {
            return new Model\Change();
        },
        'Search' => function (): Model\Search {
            return new Model\Search();
        },
        'Result' => function (): Model\Result {
            return new Model\Result();
        },
        'ResultFacet' => function (): Model\Result\Facet {
            return new Model\Result\Facet();
        },
    ],
    'resources' => [
        'Change' => function ($resource, $model = null): Resource\Change {
            return new Resource\Change($resource);
        },
        'Search' => function ($resource, $model = null): Resource\Search {
            return new Resource\Search($resource);
        },
    ],
    'models' => [
        'Change' => function (): Model\Change {
            return new Model\Change();
        },
        'Search' => function (): Model\Search {
            return new Model\Search();
        },
        'Result' => function (): Model\Result {
            return new Model\Result();
        },
        'ResultFacet' => function (): Model\Result\Facet {
            return new Model\Result\Facet();
        },
    ],
    'resources' => [
        'Change' => function ($resource, $model = null): Resource\Change {
            return new Resource\Change($resource);
        },
        'Search' => function ($resource, $model = null): Resource\Search {
            return new Resource\Search($resource);
        },
    ],
    'factories' => [
        'Term' => function (string $term): Term {
            return new Term($term);
        },
        'Query' => function (
            string $term,
            bool $caseSensitive = true,
            array $tables = [],
            array $excludeTables = [],
            ?int $limit = null,
            int $contextLength = 80,
        ): Query {
            return new Query($term, $caseSensitive, $tables, $excludeTables, $limit, $contextLength);
        },
        'EmailSearchComplete' => function (): Complete {
            return new Complete();
        },
        'EmailSearchFail' => function (): Fail {
            return new Fail();
        },
    ],
];
