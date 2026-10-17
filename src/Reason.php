<?php

namespace Nails\FindReplace;

class Reason
{
    /**
     * A matching cell is PHP-serialised and has to be edited on its own screen.
     */
    const SERIALISED = 'serialised value — edit via its own screen';

    /**
     * A declared read-only column.
     */
    const READ_ONLY = 'read-only';

    /**
     * The table does not have exactly one primary-key column.
     */
    const NO_PRIMARY_KEY = 'no primary key';

    /**
     * The live cell no longer contains the term.
     */
    const STALE = 'cell no longer contains the term';

    /**
     * The column is hidden, or is no longer a searchable column.
     */
    const EXCLUDED = 'column is excluded';

    /**
     * The row is no longer there.
     */
    const MISSING = 'row not found';
}
