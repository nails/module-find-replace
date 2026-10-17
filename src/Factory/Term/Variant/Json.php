<?php

namespace Nails\FindReplace\Factory\Term\Variant;

use Nails\FindReplace\Factory\Term\Variant;

class Json extends Variant
{
    const string ENCODING = 'JSON';

    public function getEncoding(): string
    {
        return static::ENCODING;
    }

    public function getTerm(): string
    {
        $encoded = json_encode($this->term);

        if (!is_string($encoded)) {
            return $this->term;
        }

        // Drop the JSON string quotes. The search term is the escaped contents.
        return substr($encoded, 1, mb_strlen($encoded) - 2);
    }
}
