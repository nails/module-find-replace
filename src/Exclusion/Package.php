<?php

namespace Nails\FindReplace\Exclusion;

use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\FindReplace\Interfaces\Exclusion;

class Package implements Exclusion
{
    public function hidden(): array
    {
        return [
            $this->table('Search'),
            $this->table('Result'),
            $this->table('ResultFacet'),
            $this->table('Change'),
            '/\.(token|ref)$/',
        ];
    }

    public function readOnly(): array
    {
        return [
            '/\.slug$/',
        ];
    }

    private function table(string $model): string
    {
        return Factory::model($model, Constants::MODULE_SLUG)->getTableName();
    }
}
