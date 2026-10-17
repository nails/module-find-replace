<?php

use Nails\Common\Service\PDODatabase;
use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Factory\Query;
use Nails\FindReplace\Factory\Term;
use Nails\FindReplace\Model;
use Nails\FindReplace\Resource;
use Nails\FindReplace\Service\Engine;
use Nails\FindReplace\Service\Registry;
use Nails\FindReplace\Service\Schema;

return [
    'services' => [
        'Registry' => function (): Registry {
            return new Registry();
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
    ],
    'resources' => [
        'Change' => function ($resource, $model = null): Resource\Change {
            return new Resource\Change($resource);
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
    ],
];
