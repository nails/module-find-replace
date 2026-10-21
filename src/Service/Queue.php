<?php

namespace Nails\FindReplace\Service;

use Nails\Config;
use Nails\Factory;
use Nails\FindReplace\Queue\Task\Search as SearchTask;
use Nails\Queue\Interface\Data;
use Nails\Queue\Service\Manager;

class Queue
{
    public const CONFIG_KEY = 'FIND_REPLACE_QUEUE';

    public function __construct(private ?Manager $manager = null)
    {
    }

    /**
     * Alias or queue class from FIND_REPLACE_QUEUE. Blank and non-strings use `default`.
     *
     * @phpstan-impure
     */
    public function name(): string
    {
        $configured = Config::get(self::CONFIG_KEY, 'default');

        if (!is_string($configured)) {
            return 'default';
        }

        $configured = trim($configured);

        return $configured === '' ? 'default' : $configured;
    }

    /**
     * @throws \InvalidArgumentException when FIND_REPLACE_QUEUE does not name a queue
     */
    public function assertAvailable(): void
    {
        try {
            $this->manager()->resolveQueue($this->name());
        } catch (\InvalidArgumentException | \TypeError $exception) {
            throw new \InvalidArgumentException(
                sprintf('Find & Replace is set to use the queue "%s", which is not available.', $this->name()),
                0,
                $exception,
            );
        }
    }

    public function push(int $id): void
    {
        $this->assertAvailable();
        $data = Factory::factory('Data', \Nails\Queue\Constants::MODULE_SLUG, $id);

        if (!$data instanceof Data) {
            throw new \RuntimeException('Queue is not available');
        }

        $this->manager()->push(new SearchTask(), $data, $this->name());
    }

    /**
     * Warning for the index when this queue has no live worker, or is not a queue.
     * Liveness is Manager::isProcessed(), the same check as the queue dashboard alert.
     */
    public function warning(): ?string
    {
        $manager = $this->manager();

        try {
            $queue = $manager->resolveQueue($this->name());
        } catch (\InvalidArgumentException | \TypeError) {
            return sprintf(
                'Find & Replace is set to use the queue "%s", which is not available. Searches cannot be queued.',
                $this->name(),
            );
        }

        if ($manager->isProcessed($queue)) {
            return null;
        }

        $name = $this->name();
        $class = $queue::class;
        $label = $name === $class ? $class : $name . ' (' . $class . ')';

        return sprintf(
            'There are no queue workers running for %s. Searches will stay pending until a worker is processing that queue.',
            $label,
        );
    }

    private function manager(): Manager
    {
        if ($this->manager instanceof Manager) {
            return $this->manager;
        }

        $manager = Factory::service('Manager', \Nails\Queue\Constants::MODULE_SLUG);

        if (!$manager instanceof Manager) {
            throw new \RuntimeException('Queue is not available');
        }

        return $this->manager = $manager;
    }
}
