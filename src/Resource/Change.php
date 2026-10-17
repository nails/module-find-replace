<?php

namespace Nails\FindReplace\Resource;

use Nails\Common\Resource\Entity;

class Change extends Entity
{
    public ?string $run_id = null;

    public int|string|null $search_id = null;

    public int|string|null $user_id = null;

    public ?string $source = null;

    public ?string $table_name = null;

    public int|string|null $primary_key = null;

    public ?string $column_name = null;

    public ?string $encoding = null;

    public ?string $term = null;

    public ?string $replacement = null;

    public ?string $value_before = null;

    public ?string $value_after = null;
}
