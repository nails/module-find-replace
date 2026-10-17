<?php

namespace Nails\FindReplace\Housekeeping;

use Nails\Common\Model\Base as ModelBase;
use Nails\Factory;
use Nails\FindReplace\Constants;
use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Traits\DeletesModelRows;

class Changes extends Base
{
    use DeletesModelRows;

    const THRESHOLD = 90;

    const LABEL = 'Find-replace changes';

    const DESCRIPTION = 'Deletes find-replace change rows older than 90 days';

    const CRON_EXPRESSION = '@daily';

    protected function model(): ModelBase
    {
        return Factory::model('Change', Constants::MODULE_SLUG);
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
            ['created <', $now->format('Y-m-d H:i:s')],
        ];
    }

    /**
     * @return string[]
     */
    protected function auditColumns(): array
    {
        return ['id', 'created'];
    }
}
