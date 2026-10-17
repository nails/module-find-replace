<?php

namespace Nails\FindReplace\Model\Result;

use Nails\Common\Model\Base;

class Facet extends Base
{
    const TABLE = NAILS_DB_PREFIX . 'find_replace_result_facet';

    const DESTRUCTIVE_DELETE = true;

    const AUTO_SET_USER = false;

    /**
     * @var bool|null
     */
    protected $tableAutoSetTimestamps = false;

    /**
     * @var string|null
     */
    protected $tableLabelColumn = null;
}
