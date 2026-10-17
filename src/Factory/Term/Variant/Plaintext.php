<?php

namespace Nails\FindReplace\Factory\Term\Variant;

use Nails\FindReplace\Factory\Term\Variant;

class Plaintext extends Variant
{
    const string ENCODING = 'plaintext';

    public function getEncoding(): string
    {
        return static::ENCODING;
    }

    public function getTerm(): string
    {
        return $this->term;
    }
}
