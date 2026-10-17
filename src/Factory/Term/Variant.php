<?php

namespace Nails\FindReplace\Factory\Term;

abstract class Variant
{
    public function __construct(protected string $term)
    {
    }

    abstract public function getEncoding(): string;

    abstract public function getTerm(): string;
}
