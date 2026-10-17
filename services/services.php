<?php

use Nails\Common\Service\PDODatabase;
use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Factory\Query;
use Nails\FindReplace\Factory\Term;
use Nails\FindReplace\Service\Engine;
use Nails\FindReplace\Service\Registry;
use Nails\FindReplace\Service\Schema;

return [
    'services' => [
        'Registry' => function (): Registry {
            return new Registry();
        },
        'Schema' => function (): Schema {
            $database = Factory::service('PDODatabase');
            $registry = Factory::service('Registry', Constants::MODULE_SLUG);

            if (!$database instanceof PDODatabase || !$registry instanceof Registry) {
                throw new \RuntimeException('Find-replace schema dependencies are not available');
            }

            return new Schema($database, $registry);
        },
        'Engine' => function (): Engine {
            $database = Factory::service('PDODatabase');
            $schema = Factory::service('Schema', Constants::MODULE_SLUG);

            if (!$database instanceof PDODatabase || !$schema instanceof Schema) {
                throw new \RuntimeException('Find-replace engine dependencies are not available');
            }

            return new Engine($schema, $database);
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
