<?php

namespace Nails\FindReplace\Service;

use Nails\Components;
use Nails\FindReplace\Exclusion\Package;
use Nails\FindReplace\Exclusion\Pattern;
use Nails\FindReplace\Factory\Location;
use Nails\FindReplace\Interfaces\Exclusion;
use Nails\FindReplace\Interfaces\Resolver;
use Nails\FindReplace\Interfaces\Resolver\FacetValues;
use Nails\FindReplace\Interfaces\Resolver\Facets;

class Registry
{
    /** @var list<Exclusion> */
    private array $exclusions;

    /** @var list<Resolver> */
    private array $resolvers;

    /** @var list<string>|null */
    private ?array $hidden = null;

    /** @var list<string>|null */
    private ?array $readOnly = null;

    /** @var array<string, array{label: string, values: list<string>|null}>|null */
    private ?array $catalogue = null;

    /**
     * @param list<Exclusion>|null $exclusions Discovered when null. The package exclusion is always included.
     * @param list<Resolver>|null $resolvers Discovered when null.
     */
    public function __construct(?array $exclusions = null, ?array $resolvers = null)
    {
        $this->exclusions = $exclusions ?? $this->discover(Exclusion::class, 'FindReplace\\Exclusion');
        $this->exclusions[] = new Package();
        $this->resolvers = $resolvers ?? $this->discover(Resolver::class, 'FindReplace\\Resolver');
        $this->entries();
    }

    /**
     * Hidden wins over read-only. Anything else is searchable.
     */
    public function access(string $table, string $column): string
    {
        [$hidden, $readOnly] = $this->entries();

        foreach ($hidden as $entry) {
            if (Pattern::covers($entry, $table, $column)) {
                return Location::HIDDEN;
            }
        }

        foreach ($readOnly as $entry) {
            if (Pattern::covers($entry, $table, $column)) {
                return Location::READ_ONLY;
            }
        }

        return Location::SEARCHABLE;
    }

    /**
     * @return list<Resolver>
     */
    public function resolvers(): array
    {
        return $this->resolvers;
    }

    /**
     * Declared facet labels, plus the package's own Resolver facet.
     *
     * @return array<string, string>
     */
    public function facets(): array
    {
        $labels = [];

        foreach ($this->catalogue() as $key => $facet) {
            $labels[$key] = $facet['label'];
        }

        return $labels;
    }

    /**
     * Closed values for each facet. Null when the facet accepts any string.
     * A facet stays open when any resolver that declares it omits a value list.
     *
     * @return array<string, list<string>|null>
     */
    public function facetValues(): array
    {
        $values = [];

        foreach ($this->catalogue() as $key => $facet) {
            $values[$key] = $facet['values'];
        }

        return $values;
    }

    /**
     * @return array<string, array{label: string, values: list<string>|null}>
     */
    private function catalogue(): array
    {
        if ($this->catalogue !== null) {
            return $this->catalogue;
        }

        $catalogue = [
            'resolver' => [
                'label' => 'Resolver',
                'values' => null,
            ],
        ];

        foreach ($this->resolvers as $resolver) {
            if (!$resolver instanceof Facets) {
                continue;
            }

            $typed = $this->typedValues($resolver);

            foreach ($resolver->facets() as $key => $label) {
                if (!array_key_exists($key, $catalogue)) {
                    $catalogue[$key] = [
                        'label' => $label,
                        'values' => array_key_exists($key, $typed) ? $typed[$key] : null,
                    ];
                    continue;
                }

                $catalogue[$key]['label'] = $label;
                $current = $catalogue[$key]['values'];

                if (!array_key_exists($key, $typed) || $current === null) {
                    $catalogue[$key]['values'] = null;
                    continue;
                }

                $merged = array_values(array_unique([...$current, ...$typed[$key]]));
                sort($merged);
                $catalogue[$key]['values'] = $merged;
            }
        }

        $this->catalogue = $catalogue;

        return $catalogue;
    }

    /**
     * @return array<string, list<string>>
     */
    private function typedValues(Resolver $resolver): array
    {
        if (!$resolver instanceof FacetValues) {
            return [];
        }

        $typed = [];

        foreach ($resolver->values() as $key => $accepted) {
            $typed[$key] = $this->valueList($accepted);
        }

        return $typed;
    }

    /**
     * @return list<string>
     */
    private function valueList(mixed $values): array
    {
        if (!is_array($values)) {
            throw new \InvalidArgumentException('Facet values must be a list of strings');
        }

        $clean = [];

        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('Facet values must be a list of strings');
            }

            $clean[] = $value;
        }

        $clean = array_values(array_unique($clean));
        sort($clean);

        return $clean;
    }

    /**
     * @template T of object
     * @param class-string<T> $interface
     *
     * @return list<T>
     */
    private function discover(string $interface, string $namespace): array
    {
        $objects = [];

        foreach (Components::available() as $component) {
            $classes = $component
                ->findClasses($namespace)
                ->whichImplement($interface)
                ->whichCanBeInstantiated();

            foreach ($classes as $class) {
                if (!is_string($class) || !class_exists($class)) {
                    continue;
                }

                $instance = (new \ReflectionClass($class))->newInstance();

                if (!$instance instanceof $interface) {
                    continue;
                }

                $objects[] = $instance;
            }
        }

        return $objects;
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function entries(): array
    {
        $hidden = [];
        $readOnly = [];

        foreach ($this->exclusions as $exclusion) {
            foreach ($exclusion->hidden() as $entry) {
                $hidden[] = $this->entry($entry);
            }

            foreach ($exclusion->readOnly() as $entry) {
                $readOnly[] = $this->entry($entry);
            }
        }

        if ($hidden !== $this->hidden || $readOnly !== $this->readOnly) {
            foreach ([...$hidden, ...$readOnly] as $entry) {
                Pattern::assertValid($entry);
            }

            $this->hidden = $hidden;
            $this->readOnly = $readOnly;
        }

        return [$hidden, $readOnly];
    }

    private function entry(mixed $entry): string
    {
        if (!is_string($entry)) {
            throw new \InvalidArgumentException('Exclusion entries must be strings');
        }

        return $entry;
    }
}
