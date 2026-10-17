<?php

namespace Nails\FindReplace\Factory;

use Nails\FindReplace\Factory\Term\Variant;
use Nails\FindReplace\Factory\Term\Variant\Html;
use Nails\FindReplace\Factory\Term\Variant\Json;
use Nails\FindReplace\Factory\Term\Variant\Plaintext;

class Term
{
    /** @var array<string, Variant> */
    private array $variants;

    public function __construct(string $term)
    {
        $this->variants = [
            Plaintext::ENCODING => new Plaintext($term),
            Json::ENCODING => new Json($term),
            Html::ENCODING => new Html($term),
        ];
    }

    /**
     * @return array<string, Variant>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    public function getVariant(string $encoding): Variant
    {
        if (!isset($this->variants[$encoding])) {
            throw new \DomainException(
                'The supplied encoding argument ‘' . $encoding . '’ is invalid.'
                . "\n"
                . 'It must be one of: ' . implode(', ', array_keys($this->variants))
            );
        }

        return $this->variants[$encoding];
    }
}
