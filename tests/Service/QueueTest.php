<?php

namespace Tests\FindReplace\Service;

use DateTimeInterface;
use Nails\Config;
use Nails\FindReplace\Service\Queue;
use Nails\Queue\Interface\Data;
use Nails\Queue\Interface\Queue as QueueContract;
use Nails\Queue\Interface\Task;
use Nails\Queue\Queue\Queues\DefaultQueue;
use Nails\Queue\Queue\Queues\PriorityQueue;
use Nails\Queue\Resource\Job;
use Nails\Queue\Service\Manager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nails\FindReplace\Service\Queue
 */
class QueueTest extends TestCase
{
    public function tearDown(): void
    {
        Config::set(Queue::CONFIG_KEY, 'default');
        parent::tearDown();
    }

    public function test_name_uses_the_configured_queue(): void
    {
        Config::set(Queue::CONFIG_KEY, ' priority ');

        self::assertSame('priority', (new Queue())->name());
    }

    public function test_blank_or_non_string_config_uses_default(): void
    {
        Config::set(Queue::CONFIG_KEY, '   ');
        self::assertSame('default', (new Queue())->name());

        Config::set(Queue::CONFIG_KEY, ['priority']);
        self::assertSame('default', (new Queue())->name());
    }

    public function test_push_uses_the_configured_queue(): void
    {
        Config::set(Queue::CONFIG_KEY, 'priority');
        $manager = new ManagerDouble(queue: new PriorityQueue(), job: $this->createStub(Job::class));

        (new Queue($manager))->push(17);

        self::assertSame('priority', $manager->pushedQueue);
        self::assertInstanceOf(Data::class, $manager->pushedData);
        self::assertSame(17, $manager->pushedData->get());
    }

    public function test_warning_names_an_unworked_queue(): void
    {
        Config::set(Queue::CONFIG_KEY, 'priority');
        $manager = new ManagerDouble(queue: new PriorityQueue(), processed: false);

        $warning = (new Queue($manager))->warning();

        self::assertIsString($warning);
        self::assertStringContainsString('priority', $warning);
        self::assertStringContainsString(PriorityQueue::class, $warning);
        self::assertStringContainsString('no queue workers', $warning);
    }

    public function test_warning_is_absent_when_the_queue_is_processed(): void
    {
        Config::set(Queue::CONFIG_KEY, 'default');
        $manager = new ManagerDouble(queue: new DefaultQueue(), processed: true);

        self::assertNull((new Queue($manager))->warning());
    }

    public function test_warning_when_the_queue_is_not_available(): void
    {
        Config::set(Queue::CONFIG_KEY, 'missing');
        $manager = new ManagerDouble(throwOnResolve: true);

        $warning = (new Queue($manager))->warning();

        self::assertIsString($warning);
        self::assertStringContainsString('missing', $warning);
        self::assertStringContainsString('not available', $warning);
    }

    public function test_assert_available_rejects_an_unknown_queue(): void
    {
        Config::set(Queue::CONFIG_KEY, 'missing');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing');

        (new Queue(new ManagerDouble(throwOnResolve: true)))->assertAvailable();
    }
}

class ManagerDouble extends Manager
{
    public ?string $pushedQueue = null;

    public ?Data $pushedData = null;

    public function __construct(
        private readonly ?QueueContract $queue = null,
        private readonly bool $processed = false,
        private readonly bool $throwOnResolve = false,
        private readonly ?Job $job = null,
    ) {
    }

    public function resolveQueue(string|QueueContract $alias): QueueContract
    {
        if ($this->throwOnResolve || !$this->queue instanceof QueueContract) {
            $name = is_string($alias) ? $alias : $alias::class;

            throw new \InvalidArgumentException(sprintf('Invalid queue "%s"', $name));
        }

        return $this->queue;
    }

    public function isProcessed(string|QueueContract $queue): bool
    {
        return $this->processed;
    }

    public function push(
        Task $task,
        Data $data,
        QueueContract|string|null $queue = null,
        ?DateTimeInterface $availableAt = null,
    ): Job {
        $this->pushedQueue = is_string($queue) ? $queue : null;
        $this->pushedData = $data;

        if (!$this->job instanceof Job) {
            throw new \RuntimeException('Queue job double is not available');
        }

        return $this->job;
    }
}
