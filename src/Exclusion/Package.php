<?php

namespace Nails\FindReplace\Exclusion;

use Nails\Common\Model\Base;
use Nails\FindReplace\Interfaces\Exclusion;
use Nails\FindReplace\Model\Change;
use Nails\FindReplace\Model\Result;
use Nails\FindReplace\Model\Result\Facet;
use Nails\FindReplace\Model\Search;

class Package implements Exclusion
{
    private ?Base $columnDefaults = null;

    public function hidden(): array
    {
        return [
            (new Search())->getTableName(),
            (new Result())->getTableName(),
            (new Facet())->getTableName(),
            (new Change())->getTableName(),
            $this->hiddenColumnPattern(),
        ];
    }

    public function readOnly(): array
    {
        return [
            $this->readOnlyColumnPattern(),
        ];
    }

    /**
     * Token comes from the Token trait. `ref` stays literal: no trait owns that column.
     */
    private function hiddenColumnPattern(): string
    {
        return '/\\.(' . preg_quote($this->columnDefaults()->getColumnToken(), '/') . '|ref)$/';
    }

    private function readOnlyColumnPattern(): string
    {
        $slug = $this->columnDefaults()->getColumnSlug();

        if (!is_string($slug) || $slug === '') {
            throw new \RuntimeException('Find-replace could not read the slug column name');
        }

        return '/\\.' . preg_quote($slug, '/') . '$/';
    }

    /**
     * A bare model, so the trait defaults are not a column renamed by one model.
     */
    private function columnDefaults(): Base
    {
        return $this->columnDefaults ??= new class extends Base {
            const TABLE = 'find_replace_column_defaults';
        };
    }
}
