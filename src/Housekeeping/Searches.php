<?php

namespace Nails\FindReplace\Housekeeping;

use Nails\Common\Model\Base as ModelBase;
use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Traits\DeletesModelRows;

class Searches extends Base
{
    use DeletesModelRows;

    /**
     * Same retention as change rows. A search and its result rows are one group.
     */
    const THRESHOLD = 90;

    const LABEL = 'Find-replace searches';

    const DESCRIPTION = 'Deletes non-persistent find-replace searches, and their results, 90 days after they were last run';

    const CRON_EXPRESSION = '@daily';

    protected function model(): ModelBase
    {
        return Factory::model('Search', Constants::MODULE_SLUG);
    }

    /**
     * @return array<int, mixed>
     */
    protected function where(): array
    {
        $now = Factory::factory('DateTime');

        if (!$now instanceof \DateTime) {
            throw new \RuntimeException('DateTime is not available');
        }

        $now->modify('-' . self::THRESHOLD . ' days');

        return [
            ['persistent', 0],
            ['last_run <', $now->format('Y-m-d H:i:s')],
        ];
    }

    /**
     * @return string[]
     */
    protected function auditColumns(): array
    {
        return ['id', 'last_run', 'persistent'];
    }
}
