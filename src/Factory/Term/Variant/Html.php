<?php

namespace Nails\FindReplace\Factory\Term\Variant;

use Nails\FindReplace\Factory\Term\Variant;

class Html extends Variant
{
    const string ENCODING = 'HTML';

    public function getEncoding(): string
    {
        return static::ENCODING;
    }

    public function getTerm(): string
    {
        return htmlspecialchars($this->term);
    }
}
